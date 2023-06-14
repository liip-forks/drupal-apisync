<?php

declare(strict_types = 1);

namespace Drupal\apisync_logger\Form;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Creates authorization form for API Sync.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'apisync_logger.settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'apisync_logger.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('apisync_logger.settings');

    $form['log_level'] = [
      '#title' => $this->t('API Sync Logger log level'),
      '#type' => 'radios',
      '#options' => [
        ApiSyncEvents::ERROR => $this->t('Log errors only'),
        ApiSyncEvents::WARNING => $this->t('Log warnings and errors'),
        ApiSyncEvents::NOTICE => $this->t('Log all events'),
      ],
      '#default_value' => $config->get('log_level'),
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $config = $this->config('apisync_logger.settings');
    $config->set('log_level', $formState->getValue('log_level'));
    $config->save();
    parent::submitForm($form, $formState);
  }

}
