<?php

namespace Drupal\Tests\commerce_stripe\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

abstract class StripeIntegrationTestBase extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'state_machine',
    'address',
    'profile',
    'entity_reference_revisions',
    'commerce_order',
    'commerce_payment',
    'commerce_stripe',
    'commerce_stripe_test',
  ];

  /**
   * The development publishable key.
   */
  const TEST_PUBLISHABLE_KEY = 'pk_test_EnquIXQLnqkP0knhcyRczqe600Iq21pkdd';

  /**
   * The development secret key.
   */
  const TEST_SECRET_KEY = 'sk_test_4g69Cl9vOTJxe7bUmy5TRgWE00ytQmCnep';

  /**
   * Generate a payment gateway for testing.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The Stripe gateway.
   */
  protected function generateGateway() {
    $gateway = PaymentGateway::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'plugin' => 'stripe',
      'configuration' => [
        'payment_method_types' => ['credit_card'],
        'publishable_key' => static::TEST_PUBLISHABLE_KEY,
        'secret_key' => static::TEST_SECRET_KEY,
      ],
    ]);
    $gateway->save();
    return $this->reloadEntity($gateway);
  }

}
