<?php
/**
 * Customer Review Order page
 *
 * Read-only landing page surfaced from the Customer Review Request email. The
 * page wraps every reviewable line item in a single form. The submission
 * handler that consumes the form lives in M4 (WOOPLUG-6596).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/customer-review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.8.0
 *
 * @var WC_Order $order Order being reviewed.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order instanceof WC_Order ) {
	return;
}

$date_created    = $order->get_date_created();
$customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
$customer_email  = $order->get_billing_email();
$order_number    = $order->get_order_number();
$order_date_text = $date_created ? wc_format_datetime( $date_created ) : '';

if ( '' !== $order_date_text ) {
	$order_summary = sprintf(
		/* translators: 1: order number, 2: order date */
		__( 'Order #%1$s (%2$s)', 'woocommerce' ),
		$order_number,
		$order_date_text
	);
} else {
	$order_summary = sprintf(
		/* translators: %s: order number */
		__( 'Order #%s', 'woocommerce' ),
		$order_number
	);
}

$meta_parts = array_filter(
	array(
		$customer_name,
		$customer_email,
		$order_summary,
	)
);

/**
 * Filter the eligible items rendered on the Review Order page.
 *
 * Defaults to the order's line items. Extensions can use this to hide items
 * that have already been reviewed (M5) or that are otherwise ineligible.
 *
 * @since 10.8.0
 *
 * @param WC_Order_Item[] $items Order line items.
 * @param WC_Order        $order The order being reviewed.
 */
$items = apply_filters( 'woocommerce_review_order_eligible_items', $order->get_items(), $order );

// Read the order key from the URL so the form can echo it back when posted.
// The Endpoint handler has already validated it before this template runs.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only landing page; the order key is the auth.
$raw_key   = ( isset( $_GET['key'] ) && is_string( $_GET['key'] ) ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
$order_key = is_string( $raw_key ) ? $raw_key : '';
?>
<div class="woocommerce-review-order">
	<p class="woocommerce-review-order__meta">
		<?php echo esc_html( implode( ' · ', $meta_parts ) ); ?>
	</p>

	<h1 class="woocommerce-review-order__title">
		<?php esc_html_e( 'Review your order', 'woocommerce' ); ?>
	</h1>

	<p class="woocommerce-review-order__intro">
		<?php esc_html_e( 'Loved something? Not so much? Share a quick review for what you bought. Feel free to skip any product.', 'woocommerce' ); ?>
	</p>

	<p class="woocommerce-review-order__legend">
		<?php esc_html_e( '* Mandatory fields', 'woocommerce' ); ?>
	</p>

	<?php if ( ! empty( $items ) ) : ?>
		<form
			class="woocommerce-review-order__form"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			novalidate
		>
			<input type="hidden" name="action" value="woocommerce_submit_order_reviews" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<input type="hidden" name="key" value="<?php echo esc_attr( $order_key ); ?>" />
			<?php wp_nonce_field( 'woocommerce_submit_order_reviews', '_wcnonce' ); ?>

			<ul class="woocommerce-review-order__items">
				<?php
				$row_index = 0;
				foreach ( $items as $item ) {
					if ( ! $item instanceof WC_Order_Item_Product ) {
						continue;
					}
					$product = $item->get_product();
					if ( ! $product instanceof WC_Product ) {
						continue;
					}

					wc_get_template(
						'order/customer-review-order-row.php',
						array(
							'item'      => $item,
							'product'   => $product,
							'order'     => $order,
							'row_index' => $row_index,
						)
					);

					++$row_index;
				}//end foreach
				?>
			</ul>

			<div class="woocommerce-review-order__actions">
				<button
					type="submit"
					class="woocommerce-review-order__submit button"
					disabled
				>
					<?php esc_html_e( 'Submit reviews', 'woocommerce' ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>
</div>
