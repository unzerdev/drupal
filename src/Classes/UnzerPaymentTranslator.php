<?php

namespace Drupal\commerce_unzerpayment\Classes;

class UnzerPaymentTranslator {

  public static function getPaymentMethodLabel($paymentType)
  {
    $labels = [
      'EPS'=>'EPS',
      'IDEAL'=>'iDEAL',
      'ALIPAY'=>'Alipay',
      'WECHATPAY'=>'WeChat Pay',
      'PREPAYMENT'=>'Vorkasse',
      'APPLEPAY'=>'Apple Pay',
      'SEPA-DIRECT-DEBIT-SECURED'=>'SEPA Lastschrift gesichert',
      'GOOGLEPAY'=>'Google Pay',
      'PIS'=>'PIS',
      'PAYPAL'=>'PayPal',
      'TWINT'=>'TWINT',
      'KLARNA'=>'Klarna',
      'SEPA-DIRECT-DEBIT'=>'SEPA Lastschrift',
      'POST-FINANCE-CARD'=>'Post Finance Card',
      'SOFORT'=>'Sofort',
      'CARD'=>'Kreditkarte',
      'PRZELEWY24'=>'Przelewy24',
      'POST-FINANCE-EFINANCE'=>'Post Finance eFinance',
      'INVOICE-SECURED'=>'Rechnungskauf',
      'INVOICE'=>'Rechnung',
      'INSTALLMENT-SECURED'=>'Ratenzahlung',
      'BANCONTACT'=>'Bancontact',
      'PAYLATER-INVOICE' => 'Rechnungskauf',
      'PAYLATER-INSTALLMENT' => 'Ratenkauf',
      'PAYLATER-DIRECT-DEBIT' => 'Lastschrift',
      'OPENBANKING-PIS' => 'Direkt-Überweisung'
    ];
    if (isset($labels[strtoupper($paymentType)])) {
      return $labels[strtoupper($paymentType)];
    }
    return $paymentType;
  }

}
