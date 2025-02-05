<?php

namespace Drupal\commerce_unzerpayment\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class CheckoutForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_unzerpayment\Plugin\Commerce\PaymentGateway\UnzerPayment $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $order = $payment->getOrder();

    $redirectUrl = Url::fromRoute('commerce_unzerpayment.checkout', ['commerce_payment_gateway' => $payment->getPaymentGateway()->id(), 'commerce_order' => $order->id()])->setAbsolute(true)->toString();

    $data = [
      'success' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
      'order_id' => $payment->getOrderId(),
      'payment' => $payment->getPaymentGateway()->id(),
    ];

    return $this->buildRedirectForm(
      $form,
      $form_state,
      $redirectUrl,
      $data
    );
  }

}
