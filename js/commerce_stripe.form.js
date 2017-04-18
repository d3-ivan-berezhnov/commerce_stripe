/**
 * @file
 * Javascript to generate Stripe token in PCI-compliant way.
 */

(function ($, Drupal, drupalSettings, stripe) {

  'use strict';

  /**
   * Attaches the commerceStripeForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceStripeForm behavior.
   *
   * @see Drupal.commerceStripe
   */
  Drupal.behaviors.commerceStripeForm = {
    attach: function (context) {
      var $form = $('.stripe-form', context).closest('form');
      if (drupalSettings.commerceStripe && drupalSettings.commerceStripe.publishableKey && !$form.hasClass('stripe-processed')) {
        $form.addClass('stripe-processed');
        // Clear the token every time the payment form is loaded. We only need the token
        // one time, as it is submitted to Stripe after a card is validated. If this
        // form reloads it's due to an error; received tokens are stored in the checkout pane.
        $('#stripe_token').val('');
        Stripe.setPublishableKey(drupalSettings.commerceStripe.publishableKey);
        var stripeResponseHandler = function (status, response) {
          if (response.error) {
            // Show the errors on the form
            $form.find('.payment-errors').text(response.error.message);
            $form.find('button').prop('disabled', false);
          }
          else {
            // Token contains id, last4, and card type.
            var token = response.id;
            // Insert the token into the form so it gets submitted to the server.
            $('#stripe_token').val(token);

            // And re-submit.
            $form.get(0).submit();
          }
        };

        $form.submit(function (e) {
          var $form = $(this);
          // Disable the submit button to prevent repeated clicks
          $form.find('button').prop('disabled', true);

          // Validate card form data.
          var card_number = $('.card-number').val();
          var card_expiry_month = $('.card-expiry-month').val();
          var card_expiry_year = $('.card-expiry-year').val();
          var card_cvc = $('.card-cvc').val();

          var validated = true;
          var error_messages = [];
          if (!Stripe.card.validateCardNumber(card_number)) {
            validated = false;
            error_messages.push(Drupal.t('The card number is invalid.'));
            $('.card-number').addClass('error');
          }
          else {
            $('.card-number').removeClass('error');
          }
          if (!Stripe.card.validateExpiry(card_expiry_month, card_expiry_year)) {
            validated = false;
            error_messages.push(Drupal.t('The expiry date is invalid.'));
            $('.card-expiry-month').addClass('error');
            $('.card-expiry-year').addClass('error');
          }
          else {
            $('.card-expiry-month').removeClass('error');
            $('.card-expiry-year').removeClass('error');
          }
          if (!Stripe.card.validateCVC(card_cvc)) {
            validated = false;
            error_messages.push(Drupal.t('The verification code is invalid.'));
            $('.card-cvc').addClass('error');
          }
          else {
            $('.card-cvc').removeClass('error');
          }
          if (error_messages.length > 0) {
            var payment_errors = '<ul>';
            error_messages.forEach(function(error_message) {
              payment_errors += '<li>' + error_message + '</li>';
            });
            payment_errors += '</ul>';
            $form.find('.payment-errors').html(Drupal.theme('commerceStripeError', payment_errors));
          }

          // Create token if the card form was validated.
          if (validated) {
            Stripe.card.createToken({
              number: card_number,
              exp_month: card_expiry_month,
              exp_year: card_expiry_year,
              cvc: card_cvc
            }, stripeResponseHandler);
          }

          // Prevent the form from submitting with the default action.
          if ($('.card-number').length) {
            return false;
          }
        });
      }
    }
  };

  $.extend(Drupal.theme, /** @lends Drupal.theme */{
    commerceStripeError: function (message) {
      return $('<div class="messages messages--error"></div>').html(message);
    }
  });

})(jQuery, Drupal, drupalSettings);
