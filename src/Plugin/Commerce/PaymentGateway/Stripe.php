<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Stripe payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "stripe",
 *   label = "Stripe",
 *   display_label = "Stripe",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_stripe\PluginForm\Stripe\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   js_library = "commerce_stripe/form",
 * )
 */
class Stripe extends OnsitePaymentGatewayBase implements StripeInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
    if ($this->configuration['secret_key_test']) {
      $key = ($this->getMode() == 'test') ? $this->configuration['secret_key_test'] : $this->configuration['secret_key'];
      $this->setApiKey($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setApiKey($secret_key) {
    \Stripe\Stripe::setApiKey($secret_key);
  }

  /**
   * {@inheritdoc}
   */
  public function getStripePublishableKey() {
    return $key = ($this->getMode() == 'test') ? $this->configuration['publishable_key_test'] : $this->configuration['publishable_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'secret_key_test' => '',
      'publishable_key_test' => '',
      'secret_key' => '',
      'publishable_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['secret_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Secret Key'),
      '#default_value' => $this->configuration['secret_key_test'],
      '#required' => TRUE,
    ];

    $form['publishable_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Publishable Key'),
      '#default_value' => $this->configuration['publishable_key_test'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Secret Key'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form['publishable_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Publishable Key'),
      '#default_value' => $this->configuration['publishable_key'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $modes = [
        'test' => [
          'input' => '_test',
          'livemode' => FALSE,
        ],
        'live' => [
          'input' => '',
          'livemode' => TRUE,
        ],
      ];

      // Validate secret keys.
      foreach ($modes as $mode => $mode_data) {
        $input = 'secret_key' . $mode_data['input'];
        if (!empty($values[$input])) {
          try {
            $this->setApiKey($values[$input]);
            // Make sure we use the right mode for the secret keys.
            if (\Stripe\Balance::retrieve()->offsetGet('livemode') != $mode_data['livemode']) {
              $form_state->setError($form[$input], $this->t('The @input is not for this mode: @mode.', ['@input' => $form[$input]['#title'], '@mode' => $mode]));
            }
          }
          catch (\Stripe\Error\Base $e) {
            $form_state->setError($form[$input], $this->t('Invalid @input.', ['@input' => $form[$input]['#title']]));
          }
        }
      }

      // @todo: Publishable keys validation, if possible.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['secret_key_test'] = $values['secret_key_test'];
      $this->configuration['publishable_key_test'] = $values['publishable_key_test'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['publishable_key'] = $values['publishable_key'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    if ($payment->getState()->value != 'new') {
      throw new \InvalidArgumentException('The provided payment is in an invalid state.');
    }
    $payment_method = $payment->getPaymentMethod();

    if (empty($payment_method)) {
      throw new \InvalidArgumentException('The provided payment has no payment method referenced.');
    }
    if (REQUEST_TIME >= $payment_method->getExpiresTime()) {
      throw new HardDeclineException('The provided payment method has expired');
    }
    $amount = $payment->getAmount();
    $currency_code = $payment->getAmount()->getCurrencyCode();
    $owner = $payment_method->getOwner();
    $customer_id = $owner->commerce_remote_id->getByProvider('commerce_stripe');

    $transaction_data = [
      'currency' => $currency_code,
      'amount' => $this->formatNumber($amount->getNumber()),
      'customer' => $customer_id,
      'source' => $payment_method->getRemoteId(),
      'capture' => $capture,
    ];

    try {
      $result = \Stripe\Charge::create($transaction_data);
      ErrorHelper::handleErrors($result);
    }
    catch (\Stripe\Error\Base $e) {
      ErrorHelper::handleException($e);
    }

    $payment->state = $capture ? 'capture_completed' : 'authorization';
    $payment->setRemoteId($result['id']);
    $payment->setAuthorizedTime(REQUEST_TIME);
    // @todo Find out how long an authorization is valid, set its expiration.
    if ($capture) {
      $payment->setCapturedTime(REQUEST_TIME);
    }
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    if ($payment->getState()->value != 'authorization') {
      throw new \InvalidArgumentException('Only payments in the "authorization" state can be captured.');
    }
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    try {
      $remote_id = $payment->getRemoteId();
      $decimal_amount = $amount->getNumber();
      $charge = \Stripe\Charge::retrieve($remote_id);
      $charge->amount = $this->formatNumber($decimal_amount);
      $transaction_data = [
        'amount' => $this->formatNumber($decimal_amount),
      ];
      $charge->capture($transaction_data);
    }
    catch (\Stripe\Error\Base $e) {
      ErrorHelper::handleException($e);
    }

    $payment->state = 'capture_completed';
    $payment->setAmount($amount);
    $payment->setCapturedTime(REQUEST_TIME);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    if ($payment->getState()->value != 'authorization') {
      throw new \InvalidArgumentException('Only payments in the "authorization" state can be voided.');
    }

    // Void Stripe payment - release uncaptured payment.
    try {
      $remote_id = $payment->getRemoteId();
      $decimal_amount = $payment->getAmount()->getNumber();
      $data = [
        'charge' => $remote_id,
        'amount' => $this->formatNumber($decimal_amount),
      ];
      $release_refund = \Stripe\Refund::create($data);
      ErrorHelper::handleErrors($release_refund);
    }
    catch (\Stripe\Error\Base $e) {
      ErrorHelper::handleException($e);
    }

    // Update payment.
    $payment->state = 'authorization_voided';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    if (!in_array($payment->getState()->value, [
      'capture_completed',
      'capture_partially_refunded'
    ])
    ) {
      throw new \InvalidArgumentException('Only payments in the "capture_completed" and "capture_partially_refunded" states can be refunded.');
    }
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    // Validate the requested amount.
    $balance = $payment->getBalance();
    if ($amount->greaterThan($balance)) {
      throw new InvalidRequestException(sprintf("Can't refund more than %s.", $balance->__toString()));
    }

    try {
      $remote_id = $payment->getRemoteId();
      $decimal_amount = $amount->getNumber();
      $data = [
        'charge' => $remote_id,
        'amount' => $this->formatNumber($decimal_amount),
      ];
      $refund = \Stripe\Refund::create($data);
      ErrorHelper::handleErrors($refund);
    }
    catch (\Stripe\Error\Base $e) {
      ErrorHelper::handleException($e);
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->state = 'capture_partially_refunded';
    }
    else {
      $payment->state = 'capture_refunded';
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'stripe_token'
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    $remote_payment_method = $this->doCreatePaymentMethod($payment_method, $payment_details);
    $payment_method->card_type = $this->mapCreditCardType($remote_payment_method['brand']);
    $payment_method->card_number = $remote_payment_method['last4'];
    $payment_method->card_exp_month = $remote_payment_method['exp_month'];
    $payment_method->card_exp_year = $remote_payment_method['exp_year'];
    $remote_id = $remote_payment_method['id'];
    $expires = CreditCard::calculateExpirationTimestamp($remote_payment_method['exp_month'], $remote_payment_method['exp_year']);
    $payment_method->setRemoteId($remote_id);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record.
    try {
      $owner = $payment_method->getOwner();
      if ($owner) {
        $customer_id = $owner->commerce_remote_id->getByProvider('commerce_stripe');
        $customer = \Stripe\Customer::retrieve($customer_id);
        $customer->sources->retrieve($payment_method->getRemoteId())->delete();
      }
    }
    catch (\Stripe\Error\Base $e) {
      ErrorHelper::handleException($e);
    }
    // Delete the local entity.
    $payment_method->delete();
  }


  /**
   * Creates the payment method on the gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The payment method information returned by the gateway. Notable keys:
   *   - token: The remote ID.
   *   Credit card specific keys:
   *   - card_type: The card type.
   *   - last4: The last 4 digits of the credit card number.
   *   - expiration_month: The expiration month.
   *   - expiration_year: The expiration year.
   */
  protected function doCreatePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $owner = $payment_method->getOwner();
    $customer_id = NULL;
    $customer_data = [];
    if ($owner) {
      $customer_id = $owner->commerce_remote_id->getByProvider('commerce_stripe');
      $customer_data['email'] = $owner->getEmail();
    }

    if ($customer_id) {
      // If the customer id already exists, use the Stripe form token to create the new card.
      $customer = \Stripe\Customer::retrieve($customer_id);
      // Create a payment method for an existing customer.
      $card = $customer->sources->create(['source' => $payment_details['stripe_token']]);
      return $card;
    }
    else {
      // Create both the customer and the payment method.
      try {
        $customer = \Stripe\Customer::create([
          'email' => $owner->getEmail(),
          'description' => $this->t('Customer for :mail', array(':mail' => $owner->getEmail())),
          'source' => $payment_details['stripe_token'],
        ]);
        $cards = \Stripe\Customer::retrieve($customer->id)->sources->all(['object' => 'card']);
        $cards_array = \Stripe\Util\Util::convertStripeObjectToArray([$cards]);
        $customer_id = $customer->id;
        foreach ($cards_array[0]['data'] as $card) {
          return $card;
        }
      }
      catch (\Stripe\Error\Base $e) {
        ErrorHelper::handleException($e);
      }
      if ($owner) {
        $owner->commerce_remote_id->setByProvider('commerce_stripe', $customer_id);
        $owner->save();
      }
    }

    return [];
  }

  /**
   * Maps the Stripe credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The Stripe credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected function mapCreditCardType($card_type) {
    // https://support.stripe.com/questions/which-cards-and-payment-types-can-i-accept-with-stripe.
    $map = [
      'American Express' => 'amex',
      'Diners Club' => 'dinersclub',
      'Discover' => 'discover',
      'JCB' => 'jcb',
      'MasterCard' => 'mastercard',
      'Visa' => 'visa',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * Formats the charge amount for stripe.
   *
   * @param integer $amount
   *   The amount being charged.
   *
   * @return integer
   *   The Stripe formatted amount.
   */
  protected function formatNumber($amount) {
    $amount = $amount * 100;
    $amount = number_format($amount, 0, '.', '');
    return $amount;
  }

}
