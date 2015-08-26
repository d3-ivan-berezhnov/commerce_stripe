<?php
/**
 * @file
 * This code is never called, it's just information about to use the hooks.
 */

/**
 * Add information to the metadata sent to Stripe.
 */
function hook_commerce_stripe_metadata($order) {
  return array(
    'order_number' => $order->order_number,
  );
}


/**
 * Alter the description of the order sent to Stripe in the payment details.
 */
function hook_commerce_stripe_order_description_alter(&$description, $order) {
  // Example of alteration of the description.
  if ($order->data['item_purchased'] == 'token_card') {
    $card_id = rand(1, 10000);
    $description = t('Token card id: %token_card_id', array('%token_card_id' => $card_id));
  }
}
