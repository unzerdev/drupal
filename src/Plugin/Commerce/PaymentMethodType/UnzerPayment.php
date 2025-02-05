<?php

namespace Drupal\commerce_unzerpayment\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\FailedPaymentDetailsInterface;
use Drupal\entity\BundleFieldDefinition;

#[CommercePaymentMethodType(
  id: "unzer_payment",
  label: new TranslatableMarkup('Unzer Payment'),
)]
class UnzerPayment extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    return $payment_method->getPaymentGateway()?->getPlugin()?->getDisplayLabel() ?? $this->t('Unzer Payment');
  }

}
