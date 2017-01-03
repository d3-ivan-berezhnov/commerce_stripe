<?php

namespace Drupal\commerce_stripe\PluginForm\StripeGateway;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeGatewayInterface $plugin */

    $plugin = $this->plugin;
    $integrationMode = $plugin->mode;

    if ($integrationMode == 'stripejs') {

      // Set our key to settings array.
      $element['#attached']['library'][] = 'commerce_stripe/form';
      $element['#attached']['drupalSettings']['commerceStripe'] = [
        'publishableKey' => $plugin->getStripePublishableKey(),
      ];

      $element['#attributes']['class'][] = 'stripe-form';

      $element['payment_errors'] = [
        '#type' => 'item',
        '#markup' => '<span class="payment-errors"></span>',
      ];

      $element['stripe_number'] = [
        '#type' => 'textfield',
        '#title' => t('Card number'),
        '#size' => 20,
        '#default_value' => 4242424242424242,
        '#attributes' => [
          'data-stripe' => 'number'
        ],
        '#process' => [
          'commerce_stripe_field_remove_name'
        ]
      ];


      $element['stripe_expiration'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['credit-card-form__expiration'],
        ],
        '#process' => [
          'commerce_stripe_field_remove_name'
        ]
      ];
      $element['stripe_expiration']['month'] = [
        '#type' => 'textfield',
        '#title' => t('Exp month'),
        '#size' => 2,
        '#default_value' => 01,
        '#attributes' => [
          'data-stripe' => 'exp_month'
        ],
        '#process' => [
          'commerce_stripe_field_remove_name'
        ]
      ];
      $element['stripe_expiration']['divider'] = [
        '#type' => 'item',
        '#title' => '',
        '#markup' => '<span class="credit-card-form__divider">/</span>',
      ];
      $element['stripe_expiration']['year'] = [
        '#type' => 'textfield',
        '#title' => t('Exp year'),
        '#size' => 2,
        '#default_value' => 18,
        '#attributes' => [
          'data-stripe' => 'exp_year'
        ],
        '#process' => [
          'commerce_stripe_field_remove_name'
        ]
      ];


      $element['stripe_cvc'] = [
        '#type' => 'textfield',
        '#title' => t('CVC'),
        '#size' => 4,
        '#default_value' => 111,
        '#attributes' => [
          'data-stripe' => 'cvc'
        ],
        '#process' => [
          'commerce_stripe_field_remove_name'
        ]
      ];

      // Populated by the JS library.
      $element['stripe_token'] = [
        '#type' => 'hidden',
        '#attributes' => [
          'id' => 'stripe_token'
        ]
      ];

      // To display validation errors.
      $form['errors'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="payment-errors"></div>',
      );

    }


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
