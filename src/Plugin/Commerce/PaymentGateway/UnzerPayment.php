<?php

namespace Drupal\commerce_unzerpayment\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentClient;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentLogger;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use UnzerSDK\Constants\WebhookEvents;
use UnzerSDK\Resources\TransactionTypes\Authorization;

/**
 * Provides the Unzer Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "unzer_payment",
 *   label = @Translation("Unzer Payment"),
 *   display_label = @Translation("Unzer"),
 *   payment_method_types = {"unzer_payment"},
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_unzerpayment\PluginForm\CheckoutForm",
 *   },
 * )
 */
class UnzerPayment extends OffsitePaymentGatewayBase implements SupportsRefundsInterface, SupportsAuthorizationsInterface, HasPaymentInstructionsInterface {

  const REGISTERED_EVENTS = array(
    WebhookEvents::CHARGE_CANCELED,
    WebhookEvents::AUTHORIZE_CANCELED,
    WebhookEvents::AUTHORIZE_SUCCEEDED,
    WebhookEvents::CHARGE_SUCCEEDED,
    WebhookEvents::PAYMENT_CHARGEBACK,
  );

  public function buildPaymentInstructions(PaymentInterface $payment)
  {
    if (str_contains($payment->getPaymentGateway()->getPluginId(), 'unzer_')) {
      $unzer = UnzerPaymentClient::getInstance();
      $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
      $unzer_paymentId = (\UnzerSDK\Services\IdService::getResourceTypeFromIdString($unzer_payment->getPaymentType()->getId()));
      if ($unzer_paymentId == 'ppy' || $unzer_paymentId == 'piv' || $unzer_paymentId == 'ivc') {
        $invoiceData = [
          'unzer_amount' => $unzer_payment->getInitialTransaction()->getAmount(),
          'unzer_currency' => $unzer_payment->getInitialTransaction()->getCurrency(),
          'unzer_account_holder' => $unzer_payment->getInitialTransaction()->getHolder(),
          'unzer_account_iban' => $unzer_payment->getInitialTransaction()->getIban(),
          'unzer_account_bic' => $unzer_payment->getInitialTransaction()->getBic(),
          'unzer_account_descriptor' => $unzer_payment->getInitialTransaction()->getDescriptor(),
        ];
        return \Drupal\Core\Render\Markup::create('<p>' . $this->t('Please transfer the amount to the following account:') . '</p>' .
          '<p>' .
            $this->t('Holder') . ': ' . $invoiceData['unzer_account_holder'] . '<br>' .
          $this->t('IBAN') . ': ' . $invoiceData['unzer_account_iban'] . '<br>' .
          $this->t('BIC') . ': ' . $invoiceData['unzer_account_bic'] . '<br>' .
          $this->t('Please use only this identification number as the descriptor') . ': ' . $invoiceData['unzer_account_descriptor'] . '<br>' .
          '</p>');
      }
    }
    return NULL;
  }


  public function onReturn(OrderInterface $order, Request $request) {
    $tempstore = \Drupal::service('tempstore.private')->get('commerce_unzerpayment');
    $paypage_id = $tempstore->get('paypage_id');

    $unzer = UnzerPaymentClient::getInstance();
    $paypage = $unzer->fetchPaypageV2(
      $paypage_id
    );
    $unzer_payment = $paypage->getPayments()[0];

    $unzer_payment_object = $unzer->fetchPayment($unzer_payment->getPaymentId());
    $paymentState = 'authorization';
    if (sizeof($unzer_payment_object->getCharges()) > 0) {
      $paymentState = 'completed';
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => $paymentState,
      'amount' => $order->getBalance(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'test' => $this->getMode() == 'test',
      'remote_id' => $unzer_payment->getPaymentId(),
      'remote_state' => $unzer_payment->getTransactionStatus(),
      'authorized' => $this->time->getRequestTime(),
    ]);

    $payment->save();
  }

  public function onNotify(Request $request) {
    $unzer = UnzerPaymentClient::getInstance();
    $jsonRequest = Tools::file_get_contents('php://input');
    $data = json_decode($jsonRequest, true);
    if (empty($data)) {
      UnzerPaymentLogger::getInstance()->addLog('empty webhook call', 1, false, ['server' => $_SERVER]);
      header("HTTP/1.0 404 Not Found");
      exit();
    }
    UnzerPaymentLogger::getInstance()->addLog('webhook received', 2, false, ['data' => $data]);
    if (!in_array( $data['event'], self::REGISTERED_EVENTS, true )) {
      $this->renderJson(
        array(
          'success' => true,
          'msg'     => 'event not relevant',
        )
      );
    }
    if (empty($data['paymentId'])) {
      UnzerPaymentLogger::getInstance()->addLog('no payment id in webhook event', 1, false, ['data' => $data]);
      exit();
    }
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment_ids = $payment_storage->getQuery()
      ->condition('remote_id', $data['paymentId'])
      ->accessCheck(TRUE)
      ->execute();
    foreach ($payment_ids as $payment_id) {
      $payment = $payment_storage->load($payment_id);
      switch ( $data['event'] ) {
        case WebhookEvents::CHARGE_CANCELED:
        case WebhookEvents::AUTHORIZE_CANCELED:
        case WebhookEvents::PAYMENT_CHARGEBACK:
          $payment->setState('authorization_voided');
          break;
        case WebhookEvents::AUTHORIZE_SUCCEEDED:
          $payment->setState('authorization');
          break;
        case WebhookEvents::CHARGE_SUCCEEDED:
          $payment->setState('completed');
          break;
      }
      UnzerPaymentLogger::getInstance()->addLog('webhook handled', 3, false, ['data' => $data]);
      $payment->save();
    }
    $this->renderJson(array('success' => true));
  }

  /**
   * @param $data
   * @return void
   */
  protected function renderJson($data) {
    header( 'Content-Type: application/json' );
    echo json_encode($data);
    die;
  }


  public function canVoidPayment(PaymentInterface $payment) {
    $unzer = UnzerPaymentClient::getInstance();
    $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
    if ($unzer_payment->getPaymentType()->supportsDirectPaymentCancel() && !$unzer_payment->isCanceled()) {
      return true;
    }
    return false;
  }

  public function voidPayment(PaymentInterface $payment)
  {
    $unzer = UnzerPaymentClient::getInstance();
    $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
    $unzer->cancelPayment($unzer_payment);

    $payment->setState('authorization_voided');
    $payment->setRefundedAmount($payment->getAmount());
    $payment->save();
  }

  public function canCapturePayment(PaymentInterface $payment)
  {
    if ($payment->getState()->getId() != 'completed') {
      $unzer = UnzerPaymentClient::getInstance();
      $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
      if ($unzer_payment->getAmount()->getRemaining() > 0 && $unzer_payment->getAuthorization() !== null) {
        return true;
      }
    }
    return false;
  }

  public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL)
  {
    $this->assertPaymentState($payment, ['authorization']);
    $amount = $amount ?: $payment->getAmount();

    try {
      $decimal_amount = $amount->getNumber();
      $unzer = UnzerPaymentClient::getInstance();
      $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
      $unzer->performChargeOnAuthorization($payment->getRemoteId(), $decimal_amount);
      $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
      if ($unzer_payment->getAmount()->getRemaining() > 0) {
        $newState = 'authorization';
      } else {
        $newState = 'completed';
      }
      $payment->setState($newState);
      $payment->setAmount($amount);
      $payment->save();
    }
    catch (\Exception $e) {
      UnzerPaymentLogger::getInstance()->addLog('Error capturing transaction', 1, $e, ['paymentID' => $payment->getRemoteId()]);
      throw new PaymentGatewayException('Error capturing transaction.');
    }

  }

  public function canRefundPayment(PaymentInterface $payment)
  {
    $unzer = UnzerPaymentClient::getInstance();
    $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
    if (!$unzer_payment->isCanceled() && !sizeof($unzer_payment->getCharges()) == 0) {
      return true;
    }
    return false;
  }

  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL)
  {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded', 'authorization']);
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    $unzer = UnzerPaymentClient::getInstance();
    $unzer_payment = $unzer->fetchPayment($payment->getRemoteId());
    try {
      $unzer->cancelPayment($unzer_payment, $amount->getNumber());

      $payment->setState('refunded');

      $old_refunded_amount = $payment->getRefundedAmount();
      $new_refunded_amount = $old_refunded_amount->add($amount);
      if ($new_refunded_amount->lessThan($payment->getAmount())) {
        $payment->setState('partially_refunded');
      }
      else {
        $payment->setState('refunded');
      }
      $payment->setRefundedAmount($new_refunded_amount);
    } catch (\Exception $e) {
      UnzerPaymentLogger::getInstance()->addLog('Error refunding payment', 1, $e, ['paymentID' => $payment->getRemoteId()]);
    }
    $payment->save();
  }

  public function defaultConfiguration() {
    $configuration = [
        'payment_type' => '',
      ] + parent::defaultConfiguration();
    return $configuration;
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['unzer_payment_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unzer Payment Type'),
      '#description' => $this->t('Must be the payment type string as supplied by Unzer'),
      '#default_value' => $this->configuration['unzer_payment_type'],
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'hidden',
      '#default_value' => 'live'
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['unzer_payment_type'] = $values['unzer_payment_type'];
  }

}
