<?php

namespace Drupal\commerce_unzerpayment\Classes;
use UnzerSDK\Resources\TransactionTypes\AbstractTransactionType;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Cancellation;
use UnzerSDK\Resources\TransactionTypes\Charge;

class UnzerPaymentHelper
{

  /**
   * @param $number
   * @return string
   */
  public static function prepareAmountValue($number)
  {
    return number_format($number, 2, '.', '');
  }

  /**
   * @param $string
   * @param $capitalizeFirstCharacter
   * @return array|string|string[]
   */
  public static function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
  {

    $str = str_replace('-', '', ucwords($string, '-'));

    if (!$capitalizeFirstCharacter) {
      $str = lcfirst($str);
    }

    return $str;
  }

  /**
   * @param $paymentRessourceClassName
   * @return bool
   */
  public static function paymentMethodCanAuthorize($paymentRessourceClassName)
  {
    if (class_exists("UnzerSDK\Resources\PaymentTypes\\" . $paymentRessourceClassName)) {
      if (method_exists("UnzerSDK\Resources\PaymentTypes\\" . $paymentRessourceClassName, "authorize")) {
        return true;
      }
    };
    return false;
  }

  /**
   * Returns specific  Language
   *
   * @return string
   */
  public static function getUnzerLanguage()
  {
    return \Drupal::languageManager()->getCurrentLanguage()->getId() . '_' . strtoupper(\Drupal::languageManager()->getCurrentLanguage()->getId());
  }

  /**
   * @param $state
   * @return bool
   */
  public static function isValidState($state)
  {
    return in_array(
      $state,
      [
        \UnzerSDK\Constants\PaymentState::STATE_PENDING,
        \UnzerSDK\Constants\PaymentState::STATE_COMPLETED
      ]
    );
  }

  /**
   * @param $string
   * @return false|string
   */
  public static function parsePaymentIdString($string)
  {
    $stringExploded = explode('-', $string);
    if (isset($stringExploded[1])) {
      return $stringExploded[1];
    }
    return false;
  }

  /**
   * @param $fullName
   * @return string
   * @throws \ReflectionException
   */
  public static function getPaymentClassNameByFullName($fullName)
  {
    return (new \ReflectionClass($fullName))->getShortName();
  }

  public static function getTransactions($payment_id, $order)
  {
    $unzer = UnzerPaymentClient::getInstance();
    $payment = $unzer->fetchPayment($payment_id);
    $currency     = $payment->getCurrency();
    $transactions = array();
    if ( $payment->getAuthorization() ) {
      $transactions[] = $payment->getAuthorization();
      if ( $payment->getAuthorization()->getCancellations() ) {
        $transactions = array_merge( $transactions, $payment->getAuthorization()->getCancellations() );
      }
    }
    if ( $payment->getCharges() ) {
      foreach ( $payment->getCharges() as $charge ) {
        $transactions[] = $charge;
        if ( $charge->getCancellations() ) {
          $transactions = array_merge( $transactions, $charge->getCancellations() );
        }
      }
    }
    if ( $payment->getReversals() ) {
      foreach ( $payment->getReversals() as $reversal ) {
        $transactions[] = $reversal;
      }
    }
    if ( $payment->getRefunds() ) {
      foreach ( $payment->getRefunds() as $refund ) {
        $transactions[] = $refund;
      }
    }
    $transactionTypes = array(
      Cancellation::class  => 'cancellation',
      Charge::class        => 'charge',
      Authorization::class => 'authorization',
    );
    $transactions = array_map(
      function ( AbstractTransactionType $transaction ) use ( $transactionTypes, $currency ) {
        $return         = $transaction->expose();
        $class          = get_class( $transaction );
        $return['type'] = $transactionTypes[ $class ] ?? $class;
        $return['time'] = $transaction->getDate();
        if ( method_exists( $transaction, 'getAmount' ) && method_exists( $transaction, 'getCurrency' ) ) {
          $return['amount'] = self::displayPrice( $transaction->getAmount(), $transaction->getCurrency() );
        } elseif ( isset( $return['amount'] ) ) {
          $return['amount'] = self::displayPrice( $return['amount'], $currency );
        }
        $status           = $transaction->isSuccess() ? 'success' : 'error';
        $status           = $transaction->isPending() ? 'pending' : $status;
        $return['status'] = $status;

        return $return;
      },
      $transactions
    );
    usort(
      $transactions,
      function ( $a, $b ) {
        return strcmp( $a['time'], $b['time'] );
      }
    );
    $data = array(
      'id'                => $payment->getId(),
      'paymentMethod'     => $order->payment_gateway->entity->getPluginId(),
      'paymentBaseMethod' => \UnzerSDK\Services\IdService::getResourceTypeFromIdString($payment->getPaymentType()->getId()),
      'shortID'           => $payment->getInitialTransaction()->getShortId(),
      'amount'            => self::displayPrice( $payment->getAmount()->getTotal(), $payment->getAmount()->getCurrency()  ),
      'charged'           => self::displayPrice( $payment->getAmount()->getCharged(), $payment->getAmount()->getCurrency()  ),
      'cancelled'         => self::displayPrice( $payment->getAmount()->getCanceled(), $payment->getAmount()->getCurrency()  ),
      'remaining'         => self::displayPrice( $payment->getAmount()->getRemaining(), $payment->getAmount()->getCurrency() ),
      'remainingPlain'    => $payment->getAmount()->getRemaining(),
      'transactions'      => $transactions,
      'status'            => $payment->getStateName(),
      'raw'               => print_r( $payment, true ),
    );
    return $data;
  }

  public static function displayPrice($amount, $currency)
  {
    try {
      if (!$currency) {
        $currency = 'EUR';
      }
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
      $formatted_price = $currency_formatter->format($amount, $currency);
    } catch (\Exception $e) {
      return $amount . ' - ' . $currency;
    }

    return $formatted_price;
  }


}
