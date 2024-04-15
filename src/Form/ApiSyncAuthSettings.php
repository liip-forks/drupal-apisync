<?php

declare(strict_types=1);

namespace Drupal\apisync\Form;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * API Sync Auth Settings.
 */
class ApiSyncAuthSettings extends ConfigFormBase {

  /**
   * Auth provider plugin manager service.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface
   */
  protected ApiSyncAuthProviderPluginManagerInterface $apiSyncAuth;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $apiSyncAuth
   *   Authman.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Events.
   */
  public function __construct(
      ConfigFactoryInterface $configFactory,
      ApiSyncAuthProviderPluginManagerInterface $apiSyncAuth,
      EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct($configFactory);
    $this->apiSyncAuth = $apiSyncAuth;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('config.factory'),
        $container->get('plugin.manager.apisync.auth_providers'),
        $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'apisync_auth_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['apisync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->apiSyncAuth->hasProviders()) {
      return ['#markup' => 'No auth providers have been enabled. Please enable an auth provider and create an auth config before continuing.'];
    }
    $config = $this->config('apisync.settings');
    $form = parent::buildForm($form, $form_state);
    $options = [];
    foreach ($this->apiSyncAuth->getProviders() as $provider) {
      $options[$provider->id()] = $provider->label() . ' (' . $provider->getPlugin()->label() . ')';
    }
    if (empty($options)) {
      return ['#markup' => 'No auth providers found. Please add an auth provider before continuing.'];
    }
    $options = ['' => '- None -'] + $options;
    $form['provider'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose a default auth provider'),
      '#options' => $options,
      '#default_value' => $config->get('apisync_auth_provider') ? $config->get('apisync_auth_provider') : '',
    ];
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $this->config('apisync.settings')
      ->set('apisync_auth_provider', $formState->getValue('provider') ? $formState->getValue('provider') : NULL)
      ->save();

    $this->messenger()->addStatus($this->t('Authorization settings have been saved.'));
    $this->eventDispatcher->dispatch(new ApiSyncNoticeEvent(NULL, "Authorization provider changed to %provider.", ['%provider' => $formState->getValue('provider')]), ApiSyncEvents::NOTICE);
  }

}
