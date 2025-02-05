<?php

namespace Drupal\commerce_unzerpayment\Classes;

use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;

class UnzerPaymentClient extends \UnzerSDK\Unzer {

  public static $_instance = null;

  public static $unsupportedPaymentTypes = [
    'sofort',
    'PIS',
    'giropay',
    'bancontact'
  ];

  public static function getInstance()
  {
    $config = \Drupal::config('commerce_unzerpayment.basic_settings');
    $private_key = trim($config->get('private_key'));
    if ($private_key == '') {
      return null;
    }
    if (null === self::$_instance) {
      self::$_instance = new self(
        $private_key,
        UnzerPaymentHelper::getUnzerLanguage()
      );
    }
    return self::$_instance;
  }

  /**
   * @param $paymentId
   * @param $amount
   * @return bool
   */
  public function performChargeOnAuthorization( $paymentId, $amount = null ) {
    $charge = new Charge();
    if ( $amount ) {
      $charge->setAmount($amount);
    }
    $chargeResult = false;
    try {
      $chargeResult = $this->performChargeOnPayment($paymentId, $charge);
    } catch (\UnzerSDK\Exceptions\UnzerApiException $e) {
      UnzerPaymentLogger::getInstance()->addLog('performChargeOnPayment Error', 1, $e, [
        'paymentId' => $paymentId,
        'amount' => $amount
      ]);
    } catch (\RuntimeException $e) {
      UnzerPaymentLogger::getInstance()->addLog('performChargeOnPayment Error', 1, $e, [
        'paymentId' => $paymentId,
        'amount' => $amount
      ]);
    }
    return (bool)$chargeResult;
  }

  /**
   * @return array
   * @throws \UnzerSDK\Exceptions\UnzerApiException
   */
  public static function getAvailablePaymentMethods()
  {
    $unzerClient = self::getInstance();
    if (is_null($unzerClient)) {
      return [];
    }
    $keypairResponse = $unzerClient->fetchKeypair(true);
    $availablePaymentTypes = $keypairResponse->getAvailablePaymentTypes();
    usort($availablePaymentTypes, function ($a, $b) { return strcmp(strtolower($a->type), strtolower($b->type)); });
    foreach ($availablePaymentTypes as $availablePaymentTypeKey => &$availablePaymentType) {
      if (in_array($availablePaymentType->type, self::$unsupportedPaymentTypes)) {
        unset($availablePaymentTypes[$availablePaymentTypeKey]);
      }
    }
    return $availablePaymentTypes;
  }

  public static function guessPaymentMethodClass($paymentType)
  {
    $newParts = [];
    $paymentType = str_replace('-', '_', $paymentType);
    $parts = explode('_', $paymentType);
    foreach ($parts as $part) {
      $newParts[] = ucfirst($part);
    }
    $className = join('', $newParts);
    if (class_exists("UnzerSDK\Resources\PaymentTypes\\" . $className)) {
      return lcfirst($className);
    }
    return $paymentType;
  }

  /**
   * @return array|false
   */
  public function getWebhooksList()
  {
    try {
      $webhooks = $this->fetchAllWebhooks();
      if (sizeof($webhooks) > 0) {
        return $webhooks;
      }
      return false;
    } catch (\Exception $e) {
      return false;
    }
  }

}
