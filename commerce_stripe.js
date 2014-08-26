/**
 * @file
 * Javascript to generate Stripe token in PCI-compliant way.
 */

(function ($) {
  Drupal.behaviors.stripe = {
    attach: function (context, settings) {
      if (settings.stripe.fetched == null) {
        settings.stripe.fetched = true;

        var createToken = function (cardFieldMap, responseHandler) {
          Stripe.setPublishableKey(settings.stripe.publicKey);

          var cardValues = {
            number: $('[id^=' + cardFieldMap.number +']').val(),
            cvc: $('[id^=' + cardFieldMap.cvc +']').val(),
            exp_month: $('[id^=' + cardFieldMap.exp_month +']').val(),
            exp_year: $('[id^=' + cardFieldMap.exp_year +']').val(),
            name: $('[id^=' + cardFieldMap.name +']').val()
          };

          var optionalFieldMap = {
            address_line1: 'commerce-stripe-thoroughfare',
            address_line2: 'commerce-stripe-premise',
            address_city: 'commerce-stripe-locality',
            address_state: 'commerce-stripe-administrative-area',
            address_zip: 'commerce-stripe-postal-code',
            address_country: 'commerce-stripe-country'
          };
          for (var stripeName in optionalFieldMap) {
            if (optionalFieldMap.hasOwnProperty(stripeName)) {
              var formInputElement = $('.' + optionalFieldMap[stripeName]);
              if (formInputElement.length) {
                cardValues[stripeName] = formInputElement.val();
              }
            }
          }

          Stripe.createToken(cardValues, responseHandler);
        };

        var makeResponseHandler = function (form$, errorDisplay$, onError, onSuccess) {
          return function (status, response) {
            if (response.error) {
              // Show the errors on the form.
              errorDisplay$.html($("<div class='messages error'></div>").html(response.error.message));

              onError && onError(form$);
            }
            else {
              // Token contains id, last4, and card type.
              var token = response['id'];
              // Insert the token into the form so it gets submitted to the server.
              form$.append("<input type='hidden' name='stripeToken' value='" + token + "'/>");

              onSuccess && onSuccess(form$);

              // And submit.
              form$.get(0).submit(form$);
            }
          };
        };

        $('#edit-continue').live('click', function(event) {

          // Prevent the Stripe actions to be triggered if Stripe is not selected.
          if ($("input[value*='commerce_stripe|']").is(':checked')) {
            // Do not fetch the token if cardonfile is enabled and the customer has selected an existing card.
            if ($('.form-item-commerce-payment-payment-details-cardonfile').length > 0 &&
              $("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']:checked").val() != 'new') {

              return;
            }

            $(this).addClass('auth-processing');

            // Prevent the form from submitting with the default action.
            event.preventDefault();

            // Show progress animated gif (needed for submitting after first error).
            $('.checkout-processing').show();

            // Disable the submit button to prevent repeated clicks.
            $('.form-submit').attr("disabled", "disabled");

            var cardFields = {
              number: 'edit-commerce-payment-payment-details-credit-card-number',
              cvc: 'edit-commerce-payment-payment-details-credit-card-code',
              exp_month: 'edit-commerce-payment-payment-details-credit-card-exp-month',
              exp_year: 'edit-commerce-payment-payment-details-credit-card-exp-year',
              name: 'edit-commerce-payment-payment-details-credit-card-owner'
            };

            var responseHandler = makeResponseHandler(
              $("#edit-continue").parents("form"),
              $('div.payment-errors'),
              function () {
                $(this).removeClass('auth-processing');
                // Enable the submit button to allow resubmission.
                $('.form-submit').removeAttr("disabled");
                // Hide progress animated gif.
                $('.checkout-processing').hide();
              },
              function (form$) {
                var $btnTrigger = $('.form-submit.auth-processing').eq(0);
                var trigger$ = $("<input type='hidden' />").attr('name', $btnTrigger.attr('name')).attr('value', $btnTrigger.attr('value'));
                form$.append(trigger$);
              }
            );

            createToken(cardFields, responseHandler);

            // Prevent the form from submitting with the default action.
            return false;
          }
        });
      }
    }
  }
})(jQuery);