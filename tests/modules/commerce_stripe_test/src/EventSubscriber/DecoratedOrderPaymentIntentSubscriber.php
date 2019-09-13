<?php

namespace Drupal\commerce_stripe_test\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_stripe\EventSubscriber\OrderPaymentIntentSubscriber;
use Stripe\Error\Base as StripeError;
use Stripe\PaymentIntent;

class DecoratedOrderPaymentIntentSubscriber extends OrderPaymentIntentSubscriber {

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->updateList as $intent_id => $amount) {
      try {
        PaymentIntent::update($intent_id, ['amount' => $amount]);
      }
      catch (StripeError $e) {
        // Ensure all API exceptions throw during testing.
        throw $e;
      }
    }
  }

}
