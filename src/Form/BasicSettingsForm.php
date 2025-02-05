<?php

namespace Drupal\commerce_unzerpayment\Form;

use Drupal\commerce_unzerpayment\Classes\UnzerPaymentClient;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentLogger;
use Drupal\commerce_unzerpayment\Classes\UnzerPaymentTranslator;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Provides a configuration form.
 */
class BasicSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_unzerpayment.basic_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_unzerpayment_basic_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getEditableConfigNames()[0]);

    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public Key'),
      '#description' => $this->t(''),
      '#default_value' => $config->get('public_key'),
      '#required' => true,
    ];
    $form['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private Key'),
      '#description' => $this->t(''),
      '#default_value' => $config->get('private_key'),
      '#required' => true,
    ];

    $form['charge_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Charge Mode'),
      '#options' => [
        'charge' => $this->t('charge'),
        'authorize' => $this->t('authorize')
      ],
      '#default_value' => $this->config('commerce_unzerpayment.basic_settings')->get('charge_mode') ?? 'charge',
    ];

    $form = parent::buildForm($form, $form_state);

    $request = \Drupal::request();
    if ($request->query->get('webhook_action') == 'register') {
      try {
        $unzer = UnzerPaymentClient::getInstance();
        $unzer->createWebhook(
          Url::fromRoute('commerce_payment.notify', [
            'commerce_payment_gateway' => 'unzer_payment',
          ], ['absolute' => TRUE])->toString(),
          'all'
        );
      } catch (\Exception $e) {
        UnzerPaymentLogger::getInstance()->addLog('Error creating webhook', 1, $e, []);
      }
    } elseif ($request->query->get('webhook_action') == 'delete') {
      try {
        UnzerPaymentClient::getInstance()->deleteWebhook(
          $request->query->get('webhookId')
        );
      } catch (\Exception $e) {
        UnzerPaymentLogger::getInstance()->addLog('Error deleting webhook', 1, $e, [
          'webhookId' => $request->query->get('webhookId')
        ]);
      }
    }

    if ($config->get('public_key') && $config->get('private_key')) {
      $unzer = UnzerPaymentClient::getInstance();
      $webhooks = $unzer->getWebhooksList();

      $renderable = [
        '#theme' => 'unzerwebhooks',
        '#webhooksList' => $webhooks,
        '#deleteWebhookLink' => Url::fromRoute('commerce_unzerpayment.basic_settings', ['webhook_action' => 'delete', 'webhookId' => 'UNZERWEBHOOKID'])->setAbsolute(true)->toString(),
        "#webhookCreateActionLink" => Url::fromRoute('commerce_unzerpayment.basic_settings', ['webhook_action' => 'register'])->setAbsolute(true)->toString(),
      ];
      $rendered = \Drupal::service('renderer')->renderPlain($renderable);

      $form['#suffix'] = Markup::create($rendered);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config($this->getEditableConfigNames()[0])
      ->set('public_key', $form_state->getValue('public_key'))
      ->set('private_key', $form_state->getValue('private_key'))
      ->set('charge_mode', $form_state->getValue('charge_mode'))
      ->save();

    $this->upsertPaymentMethods();
  }

  public function upsertPaymentMethods() {
    $payment_methods = UnzerPaymentClient::getAvailablePaymentMethods();
    if (empty($payment_methods)) {
      return;
    }
    foreach ($payment_methods as $payment_method) {
      $payment_gateway = \Drupal\commerce_payment\Entity\PaymentGateway::load('unzer_' . $payment_method->type);
      if ($payment_gateway) {
        $payment_gateway->delete();
      }

      $payment_gateway = \Drupal\commerce_payment\Entity\PaymentGateway::create([
        'id' => 'unzer_' . $payment_method->type,
        'label' => UnzerPaymentTranslator::getPaymentMethodLabel($payment_method->type),
        'weight' => 0,
        'plugin' => 'unzer_payment',
        'configuration' => [
          'display_label' => UnzerPaymentTranslator::getPaymentMethodLabel($payment_method->type),
          'mode' => 'live',
          'unzer_payment_type' => $payment_method->type,
          'payment_method_types' => ['unzer_payment'],
        ],
      ]);
      $payment_gateway->save();

    }
  }

}
