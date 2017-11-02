(function ($) {

  Drupal.behaviors.commerce_stripe_elements = {
    /**
     * Attach Stripe behavior to form elements.
     *
     * @param context
     *   An element to attach behavior to.
     * @param settings
     *   An object containing settings for the current context.
     */
    attach: function (context, settings)  {
      var self = this;
      if (typeof settings.stripe !== 'undefined') {
        // Create an instance of Stripe Elements
        var stripe = Stripe(settings.stripe.publicKey);
        var elements = stripe.elements();

        var style = {
          base: {
            // Add your base input styles here. For example:
            fontSize: '16px',
            lineHeight: '24px'
          }
        };

        $("#card-element").once('elements', function() {
          // Create an instance of the card Element
          self.card = elements.create('card', {style: style});
          self.card.mount(this);

          // Attach the JS behaviors just once to the available card element.
          $('body').delegate('.checkout-buttons #edit-continue', 'click', function(event) {
            // Prevent the Stripe actions from being triggered if Stripe is not the selected payment method.
            if ($("input[value*='commerce_stripe|']").is(':checked')) {
              // Do not fetch the token if cardonfile is enabled and the customer has selected an existing card.
              if ($('.form-item-commerce-payment-payment-details-cardonfile').length) {
                // If select list enabled in card on file settings
                if ($("select[name='commerce_payment[payment_details][cardonfile]']").length
                  && $("select[name='commerce_payment[payment_details][cardonfile]'] option:selected").val() != 'new') {
                  return;
                }

                // If radio buttons are enabled in card on file settings
                if ($("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']").length
                  && $("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']:checked").val() != 'new') {
                  return;
                }
              }

              $('.checkout-processing').hide();
              var form$ = $(this).closest("form", context);
              var submitButton$ = $('.checkout-buttons #edit-continue');
              var submit_text = submitButton$.val();

              submitButton$.removeAttr("disabled");
              submitButton$.removeClass('disabled');

              // Tell the form to let Stripe Elements handle the click event.
              // Prevent the Stripe actions from being triggered if Stripe is not the selected payment method.
              if ($("input[value*='commerce_stripe|']").is(':checked')) {
                var submitButton$ = $('.checkout-buttons #edit-continue');
                var submit_text = submitButton$.val();
                // console.log('Initiating Checkout.');
                submitButton$.text('Please wait...');
                submitButton$.attr("disabled", "disabled");
                submitButton$.addClass('disabled');
                submitButton$.addClass('auth-processing');
                $('.checkout-processing').show();

                // Do not fetch the token if cardonfile is enabled and the customer has selected an existing card.
                if ($('.form-item-commerce-payment-payment-details-cardonfile').length) {
                  // If select list enabled in card on file settings
                  if ($("select[name='commerce_payment[payment_details][cardonfile]']").length
                    && $("select[name='commerce_payment[payment_details][cardonfile]'] option:selected").val() != 'new') {
                    return;
                  }

                  // If radio buttons are enabled in card on file settings
                  if ($("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']").length
                    && $("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']:checked").val() != 'new') {
                    return;
                  }
                }
                stripe.createToken(self.card, Drupal.behaviors.commerce_stripe_elements.extractTokenData(form$)).then(function (result) {
                  if (result.error) {
                    // console.log('There was an error');
                    // console.log(result.error.message);
                    var submitButton$ = $('.checkout-buttons #edit-continue');
                    var submit_text = submitButton$.val();
                    submitButton$.text(submit_text);
                    submitButton$.removeAttr("disabled");
                    submitButton$.removeClass('disabled');
                    // Inform the user if there was an error
                    $("#card-errors").text(result.error.message);
                    $("#card-errors").addClass('messages');
                    $("#card-errors").addClass('error');
                    // // Prevent duplicate submissions to stripe from multiple clicks
                    // if ($(this).hasClass('auth-processing')) {
                    //   return false;
                    // }
                    submitButton$.removeClass('auth-processing');
                    $('.checkout-processing').hide();

                    Drupal.attachBehaviors(context);
                  }
                  else {
                    $("#card-errors").text('');
                    // Send the token to your server
                    $('#stripe_token').val(result.token.id);
                    var submitButton$ = $('.checkout-buttons #edit-continue');
                    var submit_text = submitButton$.val();
                    // Set a triggering element for the form.
                    var trigger$ = $("<input type='hidden' />").attr('name', submitButton$.attr('name')).attr('value', submitButton$.attr('value'));
                    form$.append(trigger$);

                    // And submit.
                    form$.get(0).submit(form$);
                  }
                });

                $("#card-errors").text('');

                event.preventDefault();
                return false;
              }
            }
          });

          $('#commerce-stripe-cardonfile-create-form').delegate('#edit-submit', 'click', function (event) {

            var form$ = $(this).closest("form", context);
            var submitButton$ = $('#edit-submit');
            var submit_text = submitButton$.val();

            submitButton$.removeAttr("disabled");
            submitButton$.removeClass('disabled');

            // Tell the form to let Stripe Elements handle the click event.

            // Prevent the Stripe actions from being triggered if Stripe is not the selected payment method.

            var submitButton$ = $('#edit-submit');
            // console.log('Initiating Checkout.');
            submitButton$.text('Please wait...');
            submitButton$.attr("disabled", "disabled");
            submitButton$.addClass('disabled');
            submitButton$.addClass('auth-processing');
            $('.checkout-processing').show();

            // Do not fetch the token if cardonfile is enabled and the customer has selected an existing card.
            if ($('.form-item-commerce-payment-payment-details-cardonfile').length) {
              // If select list enabled in card on file settings
              if ($("select[name='commerce_payment[payment_details][cardonfile]']").length
                && $("select[name='commerce_payment[payment_details][cardonfile]'] option:selected").val() != 'new') {
                return;
              }

              // If radio buttons are enabled in card on file settings
              if ($("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']").length
                && $("input[type='radio'][name='commerce_payment[payment_details][cardonfile]']:checked").val() != 'new') {
                return;
              }
            }
            stripe.createToken(self.card, Drupal.behaviors.commerce_stripe_elements.extractTokenData(form$)).then(function(result) {
              if (result.error) {
                // console.log('There was an error');
                // console.log(result.error.message);
                var submitButton$ = $('#edit-submit');
                submitButton$.text(submit_text);
                submitButton$.removeAttr("disabled");
                submitButton$.removeClass('disabled');
                // Inform the user if there was an error
                $("#card-errors").text(result.error.message);
                $("#card-errors").addClass('messages');
                $("#card-errors").addClass('error');
                // // Prevent duplicate submissions to stripe from multiple clicks
                // if ($(this).hasClass('auth-processing')) {
                //   return false;
                // }
                submitButton$.removeClass('auth-processing');
                $('.checkout-processing').hide();

                Drupal.attachBehaviors(context);
              }
              else {
                $("#card-errors").text('');
                // Send the token to your server
                $('#stripe_token').val(result.token.id);
                var submitButton$ = $('#edit-submit');;
                // Set a triggering element for the form.
                var trigger$ = $("<input type='hidden' />").attr('name', submitButton$.attr('name')).attr('value', submitButton$.attr('value'));
                form$.append(trigger$);

                // And submit.
                form$.get(0).submit(form$);
              }
            });

            $("#card-errors").text('');

            event.preventDefault();
            return false;


          });

          $('.page-admin-commerce-orders-payment').delegate('#edit-submit', 'click', function(event) {
            // Prevent the Stripe actions to be triggered if hidden field hasn't been set
            var cs_terminal = $('input[name=commerce_stripe_terminal]').val();
            if ( cs_terminal > 0) {
              var submitButton$ = $('#edit-submit');
              submitButton$.addClass('auth-processing');
              var form$ = $(this).closest("form", context);

              // Prevent the form from submitting with the default action.
              event.preventDefault();

              // Disable the submit button to prevent repeated clicks.
              $('.form-submit').attr("disabled", "disabled");

              var cardFields = {
                number: 'edit-payment-details-credit-card-number',
                cvc: 'edit-payment-details-credit-card-code',
                exp_month: 'edit-payment-details-credit-card-exp-month',
                exp_year: 'edit-payment-details-credit-card-exp-year',
                name: 'edit-payment-details-credit-card-owner'
              };

              stripe.createToken(self.card, Drupal.behaviors.commerce_stripe_elements.extractTokenData(form$)).then(function(result) {
                if (result.error) {
                  // console.log('There was an error');
                  // console.log(result.error.message);
                  var submitButton$ = $('#edit-submit');
                  var submit_text = submitButton$.val();
                  submitButton$.text(submit_text);
                  submitButton$.removeAttr("disabled");
                  submitButton$.removeClass('disabled');
                  // Inform the user if there was an error
                  $("#card-errors").text(result.error.message);
                  $("#card-errors").addClass('messages');
                  $("#card-errors").addClass('error');
                  // // Prevent duplicate submissions to stripe from multiple clicks
                  if (submitButton$.hasClass('auth-processing')) {
                    return false;
                  }
                  submitButton$.removeClass('auth-processing');
                  $('.checkout-processing').hide();

                  Drupal.attachBehaviors(context);
                }
                else {
                  $("#card-errors").text('');
                  // Send the token to your server
                  $('#stripe_token').val(result.token.id);
                  var submitButton$ = $('#edit-submit');
                  var submit_text = submitButton$.val();
                  // Set a triggering element for the form.
                  var trigger$ = $("<input type='hidden' />").attr('name', submitButton$.attr('name')).attr('value', submitButton$.attr('value'));
                  form$.append(trigger$);

                  // And submit.
                  form$.get(0).submit(form$);
                }
              });

              $("#card-errors").text('');

              event.preventDefault();
              // Prevent the form from submitting with the default action.
              return false;
            }
          });

          self.card.addEventListener('change', function (event) {
            var displayError = $('#card-errors');
            var submitButton$ = $('#edit-submit');
            var submit_text = submitButton$.val();
            if (event.error) {
              $("#card-errors").text(event.error.message);
              $("#card-errors").addClass('messages');
              $("#card-errors").addClass('error');
              submitButton$.val(submit_text);
              submitButton$.removeAttr("disabled");
              submitButton$.removeClass('disabled');
            }
            else {
              $("#card-errors").text('');
              $("#card-errors").removeClass('messages');
              $("#card-errors").removeClass('error');
            }
          });
        });
      }

    },
    detach: function(context, settings, trigger) {

      if (trigger === 'unload') {
        // Remove error message for unloaded Stripe inputs.
        $("#card-errors").removeClass('messages');
        $("#card-errors").removeClass('error');
      }
    },
    /**
     * Extract token creation data from a form.
     *
     * Stripe.createToken() should support the form as first argument and pull the information
     * from inputs marked up with the data-stripe attribute. But it does not seems to properly
     * pull value from <select> elements for the 'exp_month' and 'exp_year' fields.
     *
     */
    extractTokenData: function(form) {
      var data = {};
      $(':input[data-stripe]').not('[data-stripe="token"]').each(function() {
        var input = $(this);
        data[input.attr('data-stripe')] = input.val();
      });

      var optionalFieldMap = {
        address_line1: 'commerce-stripe-thoroughfare',
        address_line2: 'commerce-stripe-premise',
        address_city: 'commerce-stripe-locality',
        address_state: 'commerce-stripe-administrative-area',
        address_zip: 'commerce-stripe-postal-code',
        address_country: 'commerce-stripe-country',
        name: 'commerce-stripe-name'
      };

      for (var stripeName in optionalFieldMap) {
        if (optionalFieldMap.hasOwnProperty(stripeName)) {
          var formInputElement = $('.' + optionalFieldMap[stripeName]);
          if (formInputElement.length) {
            data[stripeName] = formInputElement.val();
          }
          else if (typeof Drupal.settings.commerce_stripe_address !== 'undefined') {
            // Load the values from settings if the billing address isn't on
            // the same checkout pane as the address form.
            data[stripeName] = Drupal.settings.commerce_stripe_address[stripeName];
          }
        }
      }
      return data;
    },
  };
})(jQuery);
