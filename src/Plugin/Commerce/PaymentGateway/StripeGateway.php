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
 *     "add-payment-method" = "Drupal\commerce_stripe\PluginForm\StripeGateway\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class StripeGateway extends OnsitePaymentGatewayBase implements StripeGatewayInterface {

  /**
   * The Stripe gateway used for making API calls.
   *
   * @var \Stripe\Gateway
   */
  protected $api;

  public $mode;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
    $key = ($this->getMode() == 'test') ? $this->configuration['secret_key_test'] : $this->configuration['secret_key'];
    $this->api = \Stripe\Stripe::setApiKey($key);
    $this->mode = $this->getStripeMode();
    $this->publishableKey = $this->getStripePublishableKey();

  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $operations = [];

    $access = $payment->getState()->value == 'authorization';
    $operations['capture'] = [
      'title' => $this->t('Capture'),
      'page_title' => $this->t('Capture payment'),
      'plugin_form' => 'capture-payment',
      'access' => $access,
    ];

    $access = in_array($payment->getState()->value, [
      'capture_completed',
      'capture_partially_refunded'
    ]);

    $operations['refund'] = [
      'title' => $this->t('Refund'),
      'page_title' => $this->t('Refund payment'),
      'plugin_form' => 'refund-payment',
      'access' => $access,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getStripeMode() {
    return $this->configuration['integration_type'];
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
      'integration_type' => 'stripejs',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['secret_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key (test)'),
      '#default_value' => $this->configuration['secret_key_test'],
      '#required' => TRUE,
    ];

    $form['publishable_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publishable key (test)'),
      '#default_value' => $this->configuration['publishable_key_test'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key (live)'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form['publishable_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publishable key (live)'),
      '#default_value' => $this->configuration['publishable_key'],
      '#required' => TRUE,
    ];

    $form['integration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Integration_type'),
      '#options' => ['stripejs' => 'Stripe JS'],
      '#description' => $this->t('Choose Stripe integration method: Stripe.js makes it easy to collect credit card (and other similarly sensitive) details without having the information touch your server.  Checkout is an embeddable iframe for desktop, tablet, and mobile devices. For now we only support stripe.js.'),
      '#default_value' => $this->configuration['integration_type'],
      '#required' => TRUE,
    ];

    return $form;
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
      $this->configuration['integration_type'] = $values['integration_type'];
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
    } catch (\Stripe\Exception $e) {
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
    } catch (\Stripe\Exception $e) {
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
    } catch (\Stripe\Exception $e) {
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
    } catch (\Stripe\Exception $e) {
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
    /** @var \Drupal\address\AddressInterface $address */
    $address = $payment_method->getBillingProfile()->address->first();
    $customer_id = NULL;
    $customer_data = [];
    if ($owner) {
      $customer_id = $owner->commerce_remote_id->getByProvider('commerce_stripe');
      $customer_data['email'] = $owner->getEmail();
    }

    if ($customer_id) {
      // If the customer id already exists, use the Stripe form token to create the new card.
      $customer = \Stripe\Customer::retrieve($customer_id);
      $card = $customer->sources->create(['source' => $payment_details['stripe_token']]);
      return $card;
      // Create a payment method for an existing customer.
    }
    else {
      // Create both the customer and the payment method.
      try {
        $customer = \Stripe\Customer::create([
          'email' => $owner->getEmail(),
          'description' => t('Customer for :mail', array(':mail' => $owner->getEmail())),
          'source' => $payment_details['stripe_token'],
        ]);
        $cards = \Stripe\Customer::retrieve($customer->id)->sources->all(['object' => 'card']);
        $cards_array = \Stripe\Util\Util::convertStripeObjectToArray([$cards]);
        $customer_id = $customer->id;
        $owner->commerce_remote_id->setByProvider('commerce_stripe', $customer_id);
        $owner->save();
        foreach ($cards_array[0]['data'] as $card) {
          return $card;
        }
      } catch (Exception $e) {

      }
    }

    return [

    ];
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
    $map = [
      'American Express' => 'amex',
      'China UnionPay' => 'unionpay',
      'Diners Club' => 'dinersclub',
      'Discover' => 'discover',
      'JCB' => 'jcb',
      'Maestro' => 'maestro',
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


