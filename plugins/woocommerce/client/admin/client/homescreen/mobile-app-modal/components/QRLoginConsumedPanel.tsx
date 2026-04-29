/**
 * External dependencies
 */
import React from '@wordpress/element';
import { Button } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import type { QRLoginDeviceInfo } from './useQRLoginToken';

type QRLoginConsumedPanelProps = {
	deviceInfo: QRLoginDeviceInfo | null;
	onRevoke: () => void;
	onDone?: () => void;
};

/**
 * Build the headline shown after a successful sign-in. The server-side
 * `/qr-login-scan` requires a device payload, so by the time we render
 * we always have at least an OS label. Prefer model when present.
 *
 * The leading null guard exists for the brief render between the consumed
 * status arriving and React state catching up — not as protocol compat.
 */
const buildHeadline = ( device: QRLoginDeviceInfo | null ): string => {
	const descriptor = device?.model?.trim() || device?.os?.trim() || '';
	if ( ! descriptor ) {
		return __( 'Signed in successfully', 'woocommerce' );
	}
	return sprintf(
		/* translators: %s: device model or OS, e.g. "iPhone 15" or "Android". */
		__( 'Signed in successfully on %s', 'woocommerce' ),
		descriptor
	);
};

/**
 * Build a one-line subline summarizing the device/app the merchant signed in
 * with. Skips any field the mobile app didn't send so we never render
  " · undefined" garbage.
 */
const buildSubline = ( device: QRLoginDeviceInfo | null ): string => {
	if ( ! device ) {
		return '';
	}

	const parts: string[] = [];

	if ( device.os ) {
		parts.push(
			device.os_version
				? `${ device.os } ${ device.os_version }`
				: device.os
		);
	}

	if ( device.app_version ) {
		parts.push(
			sprintf(
				/* translators: %s: mobile app version, e.g. "24.7.0". */
				__( 'App version %s', 'woocommerce' ),
				device.app_version
			)
		);
	}

	return parts.join( ' · ' );
};

/**
 * Confirmation panel shown in place of the QR code once the mobile app has
 * exchanged the token for an Application Password. Surfaces what device
 * signed in (so the merchant can spot a wrong-device scan) and offers an
 * "It wasn't you?" path that revokes the AP server-side.
 */
export const QRLoginConsumedPanel = ( {
	deviceInfo,
	onRevoke,
	onDone,
}: QRLoginConsumedPanelProps ) => {
	const headline = buildHeadline( deviceInfo );
	const subline = buildSubline( deviceInfo );

	return (
		<div
			className="qr-direct-login qr-direct-login--consumed"
			role="status"
			aria-live="polite"
		>
			<p className="qr-direct-login__consumed-headline">{ headline }</p>
			{ subline && (
				<p className="qr-direct-login__consumed-subline">{ subline }</p>
			) }

			{ onDone && (
				<Button variant="primary" onClick={ onDone }>
					{ __( 'Done', 'woocommerce' ) }
				</Button>
			) }

			<Button
				variant="link"
				className="qr-direct-login__revoke"
				onClick={ () => {
					recordEvent( 'mobile_app_qr_direct_login_revoked' );
					onRevoke();
				} }
			>
				{ __( "It wasn't you? Revoke access", 'woocommerce' ) }
			</Button>
		</div>
	);
};
