<?php
/**
 * REST API Mobile App QR Login controller.
 *
 * Handles requests to generate and exchange QR login tokens for direct mobile app
 * authentication via Application Passwords. Token generation is available to any
 * user with the manage_woocommerce capability (typically administrators and shop
 * managers); a linked WordPress.com account is no longer required.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Admin\API;

defined( 'ABSPATH' ) || exit;

/**
 * Mobile App QR Login controller.
 *
 * @internal
 */
class MobileAppQRLogin extends \WC_REST_Data_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-admin';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'mobile-app';

	/**
	 * Token TTL in seconds (5 minutes).
	 */
	const TOKEN_TTL = 300;

	/**
	 * Transient prefix for QR login tokens.
	 */
	const TOKEN_TRANSIENT_PREFIX = '_wc_qr_login_token_';

	/**
	 * Rate limit transient prefix.
	 */
	const RATE_LIMIT_PREFIX = '_wc_qr_login_rate_';

	/**
	 * Max tokens per user per 15-minute window.
	 */
	const MAX_TOKENS_PER_WINDOW = 5;

	/**
	 * Max exchange attempts per IP per 15-minute window.
	 */
	const MAX_EXCHANGE_ATTEMPTS = 10;

	/**
	 * Transient prefix for the "token consumed" record written after a successful
	 * exchange. The wc-admin UI polls a status endpoint that reads this so it can
	 * transition to a confirmation panel and surface the device that signed in.
	 */
	const CONSUMED_TRANSIENT_PREFIX = '_wc_qr_login_consumed_';

	/**
	 * Max status checks per user per 15-minute window. The polling client hits
	 * this every ~2.5s while a QR is on screen; 600/15min ≈ 40/min, comfortably
	 * above the polling rate but tight enough to short-circuit a misbehaving
	 * client or a credential-stuffing scan.
	 */
	const MAX_STATUS_CHECKS_PER_WINDOW = 600;

	/**
	 * Max revoke attempts per user per 15-minute window.
	 */
	const MAX_REVOKE_ATTEMPTS = 10;

	/**
	 * Whitelisted keys for the optional `device` payload sent by the mobile app
	 * on the exchange call. Anything outside this set is dropped before storage.
	 *
	 * `brand` is Android-only (`Build.BRAND`, e.g. "google", "samsung"); iOS
	 * doesn't have a direct analogue and clients that don't have the field
	 * just leave it absent.
	 *
	 * @var string[]
	 */
	const DEVICE_PAYLOAD_KEYS = array( 'os', 'os_version', 'model', 'brand', 'app_version' );

	/**
	 * Maximum length (chars) for any individual sanitized device-payload field.
	 * Defends against accidental or hostile bloat ending up in transients and
	 * the Application Password name.
	 */
	const DEVICE_FIELD_MAX_LENGTH = 64;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Generate a QR login token (requires authentication and `manage_woocommerce` capability).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/qr-login-token',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_token' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Exchange a QR login token for Application Password (no authentication required).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/qr-login-exchange',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'exchange_token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'token'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'device' => array(
							'required' => false,
							'type'     => 'object',
							// Sanitization happens inside the callback via
							// `sanitize_device_payload()`; we accept any object
							// shape here and whitelist server-side.
							'properties' => array(
								'os'          => array( 'type' => 'string' ),
								'os_version'  => array( 'type' => 'string' ),
								'model'       => array( 'type' => 'string' ),
								'brand'       => array( 'type' => 'string' ),
								'app_version' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Poll for token status (consumed yet?). Used by wc-admin to transition
		// the modal from "QR shown" to "Signed in successfully on {device}".
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/qr-login-status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Revoke (delete) the Application Password issued by an exchange. The
		// user must own the AP — verified inside the callback via the WP API.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/qr-login-revoke',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_password' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'uuid' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		parent::register_routes();
	}

	/**
	 * Check whether the current user can generate a QR login token.
	 *
	 * Requires the `manage_woocommerce` capability, which covers administrators and
	 * shop managers out of the box. The check is deliberately explicit (not routed
	 * through `wc_rest_check_manager_permissions()`) so it cannot be loosened by the
	 * `woocommerce_rest_check_permissions` filter that other Admin API endpoints share.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The REST request (unused).
	 * @return \WP_Error|bool True if the user has the required capability, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		unset( $request );
		// Parameter required by WP REST contract but unused here.

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you are not allowed to generate a mobile app QR login token.', 'woocommerce' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Check if Application Passwords are available.
	 *
	 * @return bool
	 */
	private function are_application_passwords_available() {
		return function_exists( 'wp_is_application_passwords_available' )
			&& wp_is_application_passwords_available();
	}

	/**
	 * Check rate limit for token generation.
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if within rate limit.
	 */
	private function check_generation_rate_limit( $user_id ) {
		$key   = self::RATE_LIMIT_PREFIX . 'gen_' . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_TOKENS_PER_WINDOW ) {
			return false;
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Check rate limit for token exchange.
	 *
	 * @return bool True if within rate limit.
	 */
	private function check_exchange_rate_limit() {
		$ip    = $this->get_client_ip();
		$key   = self::RATE_LIMIT_PREFIX . 'exc_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_EXCHANGE_ATTEMPTS ) {
			return false;
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}

	/**
	 * Validate that the configured site URL is HTTPS and return it.
	 *
	 * `is_ssl()` only tells us the current REQUEST is HTTPS — it says nothing about
	 * the canonical site URL WordPress is configured to advertise. `get_site_url()`
	 * itself is also insufficient because it passes its result through
	 * `set_url_scheme()`, which rewrites the scheme to match `is_ssl()` — so
	 * `get_site_url()` will return `https://…` whenever the request happens to be
	 * HTTPS, masking a stale `http://` `siteurl` option underneath. We therefore
	 * check the RAW stored option, which is what reflects admin configuration
	 * and what shows up in reset-password emails, webhooks, canonical redirects,
	 * etc. If that is `http://`, a misconfigured proxy that terminated TLS before
	 * reaching PHP could still cause this endpoint to hand the mobile app a cleartext
	 * site URL for the token-exchange POST.
	 *
	 * We deliberately reject (rather than silently normalizing to `https://`)
	 * because:
	 *   1. The misconfig usually affects other things (reset-password emails,
	 *      webhooks, canonical redirects). Failing loudly surfaces it.
	 *   2. Normalizing assumes the site actually serves HTTPS on the same host,
	 *      which we cannot verify from within a single request.
	 *   3. A 500 is strictly safer than a leaky success.
	 *
	 * @return string|\WP_Error The HTTPS site URL, or a WP_Error if it is not HTTPS.
	 */
	private function get_secure_site_url() {
		// Raw option: what the admin actually configured, before `set_url_scheme()`
		// inside `get_site_url()` normalizes it based on the current request's scheme.
		$raw_site_url = get_option( 'siteurl' );
		$raw_scheme   = is_string( $raw_site_url ) ? wp_parse_url( $raw_site_url, PHP_URL_SCHEME ) : null;

		if ( 'https' !== $raw_scheme ) {
			return new \WP_Error(
				'insecure_site_url',
				__( 'QR login cannot be used because the site URL is not configured for HTTPS. Please update the WordPress Address (URL) in Settings → General to use https://.', 'woocommerce' ),
				array( 'status' => 500 )
			);
		}

		// Use get_site_url() for the returned value so any scheme normalization or
		// filtering that WordPress applies downstream is preserved.
		return get_site_url();
	}

	/**
	 * Generate a QR login token.
	 *
	 * Creates a short-lived one-time token that can be exchanged for an Application
	 * Password by the mobile app. The caller is assumed to have already passed the
	 * `manage_woocommerce` capability check in `get_items_permissions_check()`.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_token( $request ) {
		unset( $request );
		// Parameter required by WP REST contract but unused here.

		// Check HTTPS.
		if ( ! is_ssl() ) {
			return new \WP_Error(
				'ssl_required',
				__( 'QR login requires an HTTPS connection.', 'woocommerce' ),
				array( 'status' => 403 )
			);
		}

		// Verify the canonical site URL is HTTPS — is_ssl() alone is not enough
		// when WordPress is behind a misconfigured proxy.
		$site_url = $this->get_secure_site_url();
		if ( is_wp_error( $site_url ) ) {
			return $site_url;
		}

		// Check Application Passwords are available.
		if ( ! $this->are_application_passwords_available() ) {
			return new \WP_Error(
				'application_passwords_unavailable',
				__( 'Application Passwords are not available on this site.', 'woocommerce' ),
				array( 'status' => 501 )
			);
		}

		// Check rate limit.
		if ( ! $this->check_generation_rate_limit( get_current_user_id() ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many QR login requests. Please try again later.', 'woocommerce' ),
				array( 'status' => 429 )
			);
		}

		// Generate a cryptographically secure token.
		$token      = wp_generate_password( 64, false );
		$token_hash = hash( 'sha256', $token );
		$expires_at = time() + self::TOKEN_TTL;

		// Store token data as a transient.
		$token_data = array(
			'user_id'    => get_current_user_id(),
			'site_url'   => $site_url,
			'expires_at' => $expires_at,
		);

		set_transient( self::TOKEN_TRANSIENT_PREFIX . $token_hash, $token_data, self::TOKEN_TTL );

		// Build the QR URL (deep link for the mobile app).
		$qr_url = sprintf(
			'woocommerce://qr-login?token=%s&siteUrl=%s',
			rawurlencode( $token ),
			rawurlencode( $site_url )
		);

		return rest_ensure_response(
			array(
				'qr_url'     => $qr_url,
				'expires_at' => $expires_at,
				'ttl'        => self::TOKEN_TTL,
			)
		);
	}

	/**
	 * Exchange a QR login token for an Application Password.
	 *
	 * This endpoint does not require authentication — the token serves
	 * as the authentication mechanism.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function exchange_token( $request ) {
		// Refuse to return credentials over a non-HTTPS request.
		if ( ! is_ssl() ) {
			return new \WP_Error(
				'ssl_required',
				__( 'QR login requires an HTTPS connection.', 'woocommerce' ),
				array( 'status' => 403 )
			);
		}

		// Check rate limit.
		if ( ! $this->check_exchange_rate_limit() ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many exchange attempts. Please try again later.', 'woocommerce' ),
				array( 'status' => 429 )
			);
		}

		// Refuse to return credentials bound to a non-HTTPS site URL — see
		// get_secure_site_url() for rationale. A token that was minted while the
		// siteurl was still https:// but has since been changed to http:// should
		// also be refused here.
		$site_url = $this->get_secure_site_url();
		if ( is_wp_error( $site_url ) ) {
			return $site_url;
		}

		$token      = $request->get_param( 'token' );
		$token_hash = hash( 'sha256', $token );
		$key        = self::TOKEN_TRANSIENT_PREFIX . $token_hash;

		// Retrieve and immediately delete the token (one-time use).
		$token_data = get_transient( $key );
		delete_transient( $key );

		if ( false === $token_data ) {
			return new \WP_Error(
				'invalid_token',
				__( 'Invalid or expired QR login token.', 'woocommerce' ),
				array( 'status' => 401 )
			);
		}

		// Validate token hasn't expired (belt and suspenders with transient TTL).
		if ( time() > $token_data['expires_at'] ) {
			return new \WP_Error(
				'token_expired',
				__( 'QR login token has expired.', 'woocommerce' ),
				array( 'status' => 401 )
			);
		}

		$user_id = $token_data['user_id'];
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error(
				'user_not_found',
				__( 'User associated with this token no longer exists.', 'woocommerce' ),
				array( 'status' => 404 )
			);
		}

		// Application Passwords may have been disabled after the token was minted.
		if ( ! $this->are_application_passwords_available() ) {
			return new \WP_Error(
				'application_passwords_unavailable',
				__( 'Application Passwords are not available on this site.', 'woocommerce' ),
				array( 'status' => 501 )
			);
		}

		// Whitelist + sanitize the optional `device` payload. Older app versions
		// don't send this; missing/empty is fine — falls back to the default AP
		// name and an empty `device` array on the consumed record.
		$device = $this->sanitize_device_payload( $request->get_param( 'device' ) );

		// Create an Application Password for the mobile app. The name is
		// descriptive (e.g. "Woo Mobile · iPhone 15 · 2026-04-28") so the user
		// can identify it later in Users → Profile → Application Passwords.
		$app_password_result = \WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name' => $this->format_application_password_name( $device ),
			)
		);

		if ( is_wp_error( $app_password_result ) ) {
			return new \WP_Error(
				'application_password_failed',
				$app_password_result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		list( $new_password, $item ) = $app_password_result;

		// Write a "consumed" record so wc-admin's polling client can transition
		// from "QR shown" to "Signed in successfully on {device}" and surface
		// a revoke button. Same TTL as the original token transient — there's
		// no value in keeping this record longer than the modal that polls it.
		set_transient(
			self::CONSUMED_TRANSIENT_PREFIX . $token_hash,
			array(
				'consumed_at' => time(),
				'user_id'     => $user_id,
				'ap_uuid'     => $item['uuid'],
				'ap_name'     => $item['name'],
				'device'      => $device,
			),
			self::TOKEN_TTL
		);

		return rest_ensure_response(
			array(
				'success'              => true,
				'user_login'           => $user->user_login,
				'user_email'           => $user->user_email,
				'user_id'              => $user_id,
				'site_url'             => $site_url,
				'application_password' => $new_password,
				'uuid'                 => $item['uuid'],
			)
		);
	}

	/**
	 * Get the status of a previously generated QR login token.
	 *
	 * Used by the wc-admin UI to poll while the QR is on screen. Returns one of:
	 *   - `pending`  — token transient exists, has not been exchanged yet.
	 *   - `consumed` — token has been exchanged; payload includes the device that
	 *                  signed in and the AP UUID so the UI can render the
	 *                  confirmation panel and (optionally) revoke the AP.
	 *   - `expired`  — neither transient exists, so the token has expired or
	 *                  was never valid for this user.
	 *
	 * The user calling this endpoint must be the same user who minted the token.
	 * That's defense in depth — tokens are 64 random chars and not realistically
	 * guessable, but cross-user status reads should be impossible regardless.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_status( $request ) {
		$user_id = get_current_user_id();

		if ( ! $this->check_status_rate_limit( $user_id ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many QR login status checks. Please try again later.', 'woocommerce' ),
				array( 'status' => 429 )
			);
		}

		$token      = (string) $request->get_param( 'token' );
		$token_hash = hash( 'sha256', $token );

		// Consumed lookup first — once a token has been exchanged we want the
		// poll to immediately reflect that state, even though the original
		// token transient was deleted by `exchange_token()`.
		$consumed = get_transient( self::CONSUMED_TRANSIENT_PREFIX . $token_hash );
		if ( is_array( $consumed ) ) {
			// Defense in depth: only the user who minted the token can see its
			// consumed details. We hide the record from cross-user reads to
			// avoid leaking that a given token has been used.
			if ( ! isset( $consumed['user_id'] ) || (int) $consumed['user_id'] !== (int) $user_id ) {
				return rest_ensure_response( array( 'status' => 'expired' ) );
			}

			return rest_ensure_response(
				array(
					'status'      => 'consumed',
					'consumed_at' => isset( $consumed['consumed_at'] ) ? (int) $consumed['consumed_at'] : null,
					'ap_uuid'     => isset( $consumed['ap_uuid'] ) ? (string) $consumed['ap_uuid'] : null,
					'ap_name'     => isset( $consumed['ap_name'] ) ? (string) $consumed['ap_name'] : null,
					'device'      => isset( $consumed['device'] ) && is_array( $consumed['device'] ) ? $consumed['device'] : array(),
				)
			);
		}

		$pending = get_transient( self::TOKEN_TRANSIENT_PREFIX . $token_hash );
		if ( is_array( $pending ) ) {
			// Same defense-in-depth ownership check.
			if ( ! isset( $pending['user_id'] ) || (int) $pending['user_id'] !== (int) $user_id ) {
				return rest_ensure_response( array( 'status' => 'expired' ) );
			}

			return rest_ensure_response(
				array(
					'status'     => 'pending',
					'expires_at' => isset( $pending['expires_at'] ) ? (int) $pending['expires_at'] : null,
				)
			);
		}

		return rest_ensure_response( array( 'status' => 'expired' ) );
	}

	/**
	 * Revoke (delete) the Application Password issued by a QR login exchange.
	 *
	 * The current user must own the AP being revoked — verified via
	 * `WP_Application_Passwords::get_user_application_password()`. We
	 * deliberately do NOT use `current_user_can( 'edit_user', $user_id )`
	 * because that would let a higher-privilege admin revoke another user's AP
	 * here; the QR flow's revoke surface is for "I just authorized this — undo,"
	 * not for site-wide AP management (which lives at Users → Profile).
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function revoke_password( $request ) {
		$user_id = get_current_user_id();

		if ( ! $this->check_revoke_rate_limit( $user_id ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many QR login revoke attempts. Please try again later.', 'woocommerce' ),
				array( 'status' => 429 )
			);
		}

		if ( ! $this->are_application_passwords_available() ) {
			return new \WP_Error(
				'application_passwords_unavailable',
				__( 'Application Passwords are not available on this site.', 'woocommerce' ),
				array( 'status' => 501 )
			);
		}

		$uuid = (string) $request->get_param( 'uuid' );

		// Ownership check: the AP must exist AND belong to the current user.
		$ap = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );
		if ( ! is_array( $ap ) ) {
			return new \WP_Error(
				'application_password_not_found',
				__( 'No matching Application Password to revoke.', 'woocommerce' ),
				array( 'status' => 404 )
			);
		}

		$deleted = \WP_Application_Passwords::delete_application_password( $user_id, $uuid );
		if ( true !== $deleted ) {
			return new \WP_Error(
				'application_password_revoke_failed',
				__( 'Could not revoke the Application Password. Please try again.', 'woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'uuid'    => $uuid,
			)
		);
	}

	/**
	 * Whitelist + sanitize the optional `device` payload sent by the mobile app
	 * on the exchange call.
	 *
	 * Returns an array of strings keyed by the whitelisted keys defined in
	 * `DEVICE_PAYLOAD_KEYS`. Anything outside that whitelist is dropped. Each
	 * value is run through `sanitize_text_field()` and capped at
	 * `DEVICE_FIELD_MAX_LENGTH` characters. The function is total — pass `null`
	 * or anything non-array and you get back `array()`.
	 *
	 * @param mixed $device Raw payload from the request.
	 * @return array<string, string>
	 */
	private function sanitize_device_payload( $device ) {
		if ( ! is_array( $device ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( self::DEVICE_PAYLOAD_KEYS as $key ) {
			if ( ! isset( $device[ $key ] ) || ! is_scalar( $device[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $device[ $key ] );
			if ( '' === $value ) {
				continue;
			}
			if ( strlen( $value ) > self::DEVICE_FIELD_MAX_LENGTH ) {
				$value = substr( $value, 0, self::DEVICE_FIELD_MAX_LENGTH );
			}
			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Build a descriptive name for the Application Password issued by the QR
	 * login exchange.
	 *
	 * Preferred: `Woo Mobile · iPhone 15 · 2026-04-28` (model + ISO date).
	 * Falls back to `Woo Mobile · iOS · 2026-04-28` when only the OS is known.
	 * Falls back to the legacy literal `WooCommerce Mobile App (QR Login)` if
	 * neither model nor OS is available — that keeps older mobile clients (which
	 * don't send the `device` payload) working without changing their visible
	 * AP name.
	 *
	 * The name is what the merchant sees in WP admin → Users → Profile →
	 * Application Passwords, so it should be human-readable, single-line, and
	 * not contain anything that would only make sense to an engineer.
	 *
	 * @param array<string, string> $device Sanitized device payload.
	 * @return string
	 */
	private function format_application_password_name( array $device ): string {
		$model = isset( $device['model'] ) ? trim( $device['model'] ) : '';
		$os    = isset( $device['os'] ) ? trim( $device['os'] ) : '';

		$descriptor = '';
		if ( '' !== $model ) {
			$descriptor = $model;
		} elseif ( '' !== $os ) {
			$descriptor = $os;
		}

		if ( '' === $descriptor ) {
			// Legacy fallback — preserves the existing AP name format for
			// older app versions that don't send a device payload.
			return __( 'WooCommerce Mobile App (QR Login)', 'woocommerce' );
		}

		// Use the site's configured timezone so the date the merchant sees in
		// the AP list matches what they'd see in the rest of wp-admin.
		$date = wp_date( 'Y-m-d' );

		/* translators: 1: device descriptor (model or OS, e.g. "iPhone 15"). 2: ISO date the AP was created. */
		return sprintf( __( 'Woo Mobile · %1$s · %2$s', 'woocommerce' ), $descriptor, $date );
	}

	/**
	 * Per-user rate limit for the polling status endpoint.
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if within rate limit.
	 */
	private function check_status_rate_limit( $user_id ) {
		$key   = self::RATE_LIMIT_PREFIX . 'sta_' . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_STATUS_CHECKS_PER_WINDOW ) {
			return false;
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Per-user rate limit for the revoke endpoint.
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if within rate limit.
	 */
	private function check_revoke_rate_limit( $user_id ) {
		$key   = self::RATE_LIMIT_PREFIX . 'rev_' . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::MAX_REVOKE_ATTEMPTS ) {
			return false;
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}
}
