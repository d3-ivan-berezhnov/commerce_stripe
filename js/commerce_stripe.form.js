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
            if (typeof drupalSettings.commerceStripe.fetched == 'undefined') {
                drupalSettings.commerceStripe.fetched = true;
                // Clear the token every time the payment form is loaded. We only need the token
                // one time, as it is submitted to Stripe after a card is validated. If this
                // form reloads it's due to an error; received tokens are stored in the checkout pane.
                $('#stripe_token').val('');
                Stripe.setPublishableKey(drupalSettings.commerceStripe.publishableKey);
                var $form = $('.stripe-form', context).closest('form');
                var stripeResponseHandler = function (status, response) {
                    if (response.error) {
                        // Show the errors on the form
                        $form.find('.payment-errors').text(response.error.message);
                        $form.find('button').prop('disabled', false);
                    } else {
                        // token contains id, last4, and card type
                        var token = response.id;
                        // Insert the token into the form so it gets submitted to the server
                        $('#stripe_token').val(token);

                        // and re-submit
                        $form.get(0).submit();
                    }
                };
                $form.submit(function (e) {
                    var $form = $(this);
                    // Disable the submit button to prevent repeated clicks
                    $form.find('button').prop('disabled', true);
                    Stripe.card.createToken($form, stripeResponseHandler);
                    // Prevent the form from submitting with the default action
                    return false;
                });
            }
        },
    };

})(jQuery, Drupal, drupalSettings);
