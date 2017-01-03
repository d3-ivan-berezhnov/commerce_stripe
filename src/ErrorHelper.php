<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_payment\Exception\AuthenticationException;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\SoftDeclineException;

/**
 * Translates Stripe exceptions and errors into Commerce exceptions.
 */
class ErrorHelper {

  /**
   * Translates Stripe exceptions into Commerce exceptions.
   *
   * @param \Stripe\Exception $exception
   *   The Stripe exception.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The Commerce exception.
   */
  public static function handleException(\Stripe\Exception $exception) {
//    if ($exception instanceof \Stripe\Exception\Authentication) {
//      throw new AuthenticationException('Stripe authentication failed.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\Authorization) {
//      throw new AuthenticationException('The used API key is not authorized to perform the attempted action.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\NotFound) {
//      throw new InvalidRequestException('Stripe resource not found.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\UpgradeRequired) {
//      throw new InvalidRequestException('The Stripe client library needs to be updated.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\TooManyRequests) {
//      throw new InvalidRequestException('Too many requests.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\ServerError) {
//      throw new InvalidResponseException('Server error.');
//    }
//    elseif ($exception instanceof \Stripe\Exception\DownForMaintenance) {
//      throw new InvalidResponseException('Request timed out.');
//    }
//    else {
//      throw new InvalidResponseException($exception->getMessage());
//    }
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
//    if ($result['_values']['status'] == 'succeeded') {
//      return;
//    }
//    $errors = $result->errors->deepAll();
//    if (!empty($errors)) {
//      // https://developers.stripepayments.com/reference/general/validation-errors/all/php
//      // Validation errors can be due to a module error (mapped to
//      // InvalidRequestException) or due to a user input error (mapped to
//      // a HardDeclineException).
//      $hard_decline_codes = [81813, 91828, 81736, 81737, 81750, 91568];
//      foreach($errors AS $error) {
//        if (in_array($error->code, $hard_decline_codes)) {
//          throw new HardDeclineException($error->message, $error->code);
//        }
//        else {
//          throw new InvalidRequestException($error->message, $error->code);
//        }
//      }
//    }
//
//    // Both verification and the transaction can result in the same errors.
//    $error_statuses = [
//      'settlement_declined',
//      'gateway_rejected',
//      'processor_declined',
//    ];
//    if ($result->verification && in_array($result->verification->status, $error_statuses)) {
//      $error = $result->verification;
//      $status = $result->verification->status;
//    }
//    elseif ($result->transaction && in_array($result->verification->status, $error_statuses)) {
//      $error = $result->verification;
//      $status = $result->verification->status;
//    }
//
//    if ($status == 'settlement_declined') {
//      $code = $error->processorSettlementResponseCode;
//      $text = $error->processorSettlementResponseText;
//      throw new HardDeclineException($text, $code);
//    }
//    elseif ($status == 'gateway_rejected') {
//      $reason = $error->gatewayRejectionReason;
//      throw new HardDeclineException('Rejected by the gateway. Reason: ' . $reason);
//    }
//    elseif ($status == 'processor_declined') {
//      // https://developers.stripepayments.com/reference/general/processor-responses/authorization-responses
//      $soft_decline_codes = [
//        2000, 2001, 2002, 2003, 2009, 2016, 2021, 2025, 2026, 2033, 2034, 2035,
//        2038, 2040, 2042, 2046, 2048, 2050, 2054, 2057, 2062,
//      ];
//      $code = $error->processorResponseCode;
//      $text = $error->processorResponseText;
//      if (!empty($error->additionalProcessorResponse)) {
//        $text .= ' (' . $error->additionalProcessorResponse . ')';
//      }
//      if (in_array($code, $soft_decline_codes) || ($code >= 2092 && $code <= 3000)) {
//        throw new SoftDeclineException($text, $code);
//      }
//      else {
//        throw new HardDeclineException($text, $code);
//      }
//    }
  }

}
