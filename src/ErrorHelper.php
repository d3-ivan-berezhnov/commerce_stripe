<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_payment\Exception\AuthenticationException;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\InvalidResponseException;

/**
 * Translates Stripe exceptions and errors into Commerce exceptions.
 */
class ErrorHelper {

  /**
   * Translates Stripe exceptions into Commerce exceptions.
   *
   * @param \Stripe\Error\Base $exception
   *   The Stripe exception.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The Commerce exception.
   */
  public static function handleException(\Stripe\Error\Base $exception) {
    if ($exception instanceof \Stripe\Error\Card) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new DeclineException('We encountered an error processing your card details. Please verify your details and try again.');
    }
    elseif ($exception instanceof \Stripe\Error\RateLimit) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new InvalidRequestException('Too many requests.');
    }
    elseif ($exception instanceof \Stripe\Error\InvalidRequest) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new InvalidRequestException('Invalid parameters were supplied to Stripe\'s API.');
    }
    elseif ($exception instanceof \Stripe\Error\Authentication) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new AuthenticationException('Stripe authentication failed.');
    }
    elseif ($exception instanceof \Stripe\Error\ApiConnection) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new InvalidResponseException('Network communication with Stripe failed.');
    }
    elseif ($exception instanceof \Stripe\Error\ApiConnection) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new InvalidResponseException('Network communication with Stripe failed.');
    }
    elseif ($exception instanceof \Stripe\Error\Base) {
      \Drupal::logger('commerce_stripe')->warning($exception->getMessage());
      throw new InvalidResponseException('There was an error with Stripe request.');
    }
    else {
      throw new InvalidResponseException($exception->getMessage());
    }
  }

  /**
   * Translates Stripe errors into Commerce exceptions.
   *
   * @param object $result
   *   The Stripe result object.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The Commerce exception.
   */
  public static function handleErrors($result) {
    if ($result['_values']['status'] == 'succeeded') {
      return;
    }
    $errors = $result->errors->deepAll();
    if (!empty($errors)) {
      // https://stripe.com/docs/api?lang=php#errors
      // Validation errors can be due to a module error (mapped to
      // InvalidRequestException) or due to a user input error (mapped to
      // a HardDeclineException).
      $hard_decline_codes = [500, 502, 503, 504];
      foreach($errors AS $error) {
        if (in_array($error->code, $hard_decline_codes)) {
          throw new HardDeclineException($error->message, $error->code);
        }
        else {
          throw new InvalidRequestException($error->message, $error->code);
        }
      }
    }

  }

}
