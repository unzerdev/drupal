<?php

namespace Drupal\commerce_unzerpayment\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Controller\PaymentCheckoutController;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentClient;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentLogger;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends \Drupal\commerce_checkout\Controller\CheckoutController {

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return RedirectResponse
   *   A response.
   */
  public function processPayment(OrderInterface $commerce_order, PaymentGatewayInterface $commerce_payment_gateway, Request $request) {

    if (!$request->get('commerce_order')) {
      die('Missing mandatory parameters "commerce_order"');
    }

    $tempstore = \Drupal::service('tempstore.private')->get('commerce_unzerpayment');

    if ($request->get('cancel')) {
      $cancelUrl = $request->get('cancel');
    } else {
      $cancelUrl = $tempstore->get('order_cancel_url_' . $request->get('commerce_order')->id());
    }
    if ($request->get('success')) {
      $successUrl = $request->get('success');
    } else {
      $successUrl = $tempstore->get('order_success_url_' . $request->get('commerce_order')->id());
    }

    if (!$request->get('commerce_payment_gateway')) {
      return new RedirectResponse($cancelUrl);
    }
    if (!str_contains($request->get('commerce_payment_gateway')->id(), 'unzer_')) {
      return new RedirectResponse($cancelUrl);
    }
    $paypage_id = $tempstore->get('paypage_id');

    $unzer = UnzerPaymentClient::getInstance();
    $paypage = $unzer->fetchPaypageV2(
      $paypage_id
    );

    $validOrderState = false;

    if (is_array($paypage->getPayments()) && sizeof($paypage->getPayments()) > 0) {
      $payment = $paypage->getPayments()[0];
      if ($payment->getTransactionStatus() == \UnzerSDK\Constants\TransactionStatus::STATUS_SUCCESS ||
          $payment->getTransactionStatus() == \UnzerSDK\Constants\TransactionStatus::STATUS_PENDING) {
        $validOrderState = true;
      }
    }

    $commerce_order->set('checkout_step', 'payment');
    $commerce_order->save();

    if (!$validOrderState) {
      UnzerPaymentLogger::getInstance()->addLog('Invalid order State', 1, false, [
        'paypageId' => $paypage_id,
        'orderState' => $payment->getTransactionStatus()
      ]);
      return new RedirectResponse($cancelUrl);
    }

    return new RedirectResponse($successUrl);
  }

}
