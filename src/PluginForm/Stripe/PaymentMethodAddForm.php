<?php

namespace Drupal\commerce_stripe\PluginForm\Stripe;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $element = parent::buildCreditCardForm($element, $form_state);

    // Alter the form with Stripe specific needs.
    $element['#attributes']['class'][] = 'stripe-form';

    // Set our key to settings array.
    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface $plugin */
    $plugin = $this->plugin;
    $element['#attached']['drupalSettings']['commerceStripe'] = [
      'publishableKey' => $plugin->getStripePublishableKey(),
    ];

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div class="payment-errors"></div>',
      '#weight' => -200,
    ];

    // Add class identifiers for card data.
    $element['number']['#attributes']['class'][] = 'card-number';
    $element['expiration']['month']['#attributes']['class'][] = 'card-expiry-month';
    $element['expiration']['year']['#attributes']['class'][] = 'card-expiry-year';
    $element['security_code']['#title'] = t('CVC');
    $element['security_code']['#attributes']['class'][] = 'card-cvc';

    // Populated by the JS library.
    $element['stripe_token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'stripe_token'
      ]
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Add the stripe attribute to the postal code field.
    $form['billing_information']['address']['widget'][0]['address_line1']['#attributes']['data-stripe'] = 'address_line1';
    $form['billing_information']['address']['widget'][0]['address_line2']['#attributes']['data-stripe'] = 'address_line2';
    $form['billing_information']['address']['widget'][0]['locality']['#attributes']['data-stripe'] = 'address_city';
    $form['billing_information']['address']['widget'][0]['postal_code']['#attributes']['data-stripe'] = 'address_zip';
    $form['billing_information']['address']['widget'][0]['country_code']['#attributes']['data-stripe'] = 'address_country';
    return $form;
  }

}
