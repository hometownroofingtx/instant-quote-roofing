<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$customer_details = $v['customerDetails'] ?? array();
if ( ! empty( $customer_details ) ) : ?>
<section class="report-customer-details" aria-label="Customer details">
 <strong>Prepared for: <?php echo esc_html( $customer_details['name'] ?? $v['firstName'] ?? '' ); ?></strong>

 <?php if ( ! empty( $customer_details['email'] ) ) : ?><span>Email: <?php echo esc_html( $customer_details['email'] ); ?></span><?php endif; ?>
 <?php if ( ! empty( $customer_details['phone'] ) ) : ?><span>Phone: <?php echo esc_html( $customer_details['phone'] ); ?></span><?php endif; ?>
</section>
<?php endif; ?>
