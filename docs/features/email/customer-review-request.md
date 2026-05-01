---
post_title: Customer review request email
sidebar_label: Customer review request
---

# Customer Review Request

## Overview

The Customer Review Request feature emails customers a few days after their order is marked completed, asking them to review the products they bought. The email links to a dedicated tokenised landing page where the customer rates each line item, optionally writes a review, and submits everything in a single form.

The feature is implemented in the `Automattic\WooCommerce\Internal\OrderReviews` namespace and is wired into the WC dependency container so each sub-service registers its hooks on instantiation.

## Sub-services

| Class | Responsibility |
| ----- | -------------- |
| `Scheduler` | Schedules the delayed review-request email via Action Scheduler when an order moves to `completed`, and unschedules it on cancellation, refund, trash, or delete. |
| `Endpoint` | Registers the standalone `/review-order/{id}/?key={order_key}` rewrite, runs the gating checks (key match, eligible status, customer ownership), and renders the landing-page template. |
| `SubmissionHandler` | AJAX handler that consumes the form post. Per-row outcome reporting (`ok`, `pending_moderation`, `error`); honours WordPress's `comment_moderation` option; sets `verified=1` commentmeta on every inserted review. |
| `ItemEligibility` | Decides per line item whether the row should be a form, a locked "already reviewed" row, or skipped. Provides the default callback that excludes fully-refunded line items. |
| `StarRating` | Server-side renderer for the accessible 5-star rating control (radio inputs + SVG visuals + caption). |

## Tokenised URL helper

```php
$url = wc_get_review_order_url( $order );
```

Returns the tokenised review-order URL for a given `WC_Order`. Use this rather than constructing the URL manually so the helper's filter and pretty/plain-permalink fallback both apply.

## Filter and action reference

### Filters

- `woocommerce_should_send_review_request` (`bool`, `WC_Order`)<br>
  Return `false` to skip scheduling the email for a specific order.

- `woocommerce_review_request_delay_seconds` (`int`, `WC_Order`)<br>
  Override the delay (in seconds) before the email fires. Defaults to the value configured on the email settings screen.

- `woocommerce_review_order_url` (`string`, `WC_Order`)<br>
  Replace the URL emitted by `wc_get_review_order_url()`.

- `woocommerce_review_order_eligible_statuses` (`string[]`, `WC_Order`)<br>
  Widen or narrow the order statuses that pass the route-level gate. Defaults to `[ 'completed' ]`.

- `woocommerce_review_order_eligible_items` (`WC_Order_Item[]`, `WC_Order`)<br>
  Filter the line items rendered on the page. The default callback excludes fully-refunded items.

- `woocommerce_review_order_item_already_reviewed` (`bool`, `int $product_id`, `WC_Order`, `string $customer_email`)<br>
  Override the per-item "already reviewed" decision. Useful when reviews live somewhere other than `wp_comments`.

- `woocommerce_review_order_rating_labels` (`array<int,string>`)<br>
  Customise the 1-5 star labels surfaced beside the control. Defaults to `Very poor / Not that bad / Average / Good / Perfect`.

### Actions

- `woocommerce_review_order_form_fields` (`WC_Order_Item_Product`, `WC_Product`, `WC_Order`, `int $row_index`)<br>
  Fires inside each form row, after the rating + textarea. Echo extra fields directly.

- `woocommerce_review_order_submitted` (`int[] $comment_ids`, `WC_Order`)<br>
  Fires after a successful submission, once any pending or approved comments have been inserted.

## Theme overrides

The page renders through `wc_get_template()`, so themes can override any of:

- `templates/order/customer-review-order.php` — the page wrapper.
- `templates/order/customer-review-order-row.php` — one form row per item.
- `templates/order/customer-review-order-row-reviewed.php` — locked variant for items the customer already reviewed.
- `templates/order/customer-review-order-empty.php` — thank-you view rendered when nothing is left to review.
- `templates/order/star-rating.php` — the accessible star-rating control partial.

Copy any of those files into `yourtheme/woocommerce/order/{name}.php` to override.

## Email template overrides

The HTML and plain-text bodies of the email itself follow standard WC conventions:

- `templates/emails/customer-review-request.php`
- `templates/emails/plain/customer-review-request.php`
- `templates/emails/block/customer-review-request.php` (block-based email editor)

## Accessibility notes

- The star-rating control wraps a native `radiogroup` of 5 inputs. The visual stars are SVG; the inputs themselves are visually hidden but remain in the accessibility tree.
- Keyboard navigation is supported via Arrow keys and Home/End.
- A live caption (`aria-live="polite"`) announces the selected rating's label.
- The "Required" indicator on the rating label is exposed to screen readers via a `.screen-reader-text` span alongside the visual asterisk.
