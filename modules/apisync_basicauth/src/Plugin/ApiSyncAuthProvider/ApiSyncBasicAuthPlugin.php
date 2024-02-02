<?php

declare(strict_types = 1);

namespace Drupal\apisync_basicauth\Plugin\ApiSyncAuthProvider;

use Drupal\apisync\ApiSyncAuthProviderPluginBase;
use Drupal\apisync\Consumer\ApiSyncCredentials;
use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\OAuth2\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic Auth plugin.
 *
 * @Plugin(
 *   id = "basic_auth",
 *   label = @Translation("Basic Authentication")
 * )
 */
class ApiSyncBasicAuthPlugin extends ApiSyncAuthProviderPluginBase {

  use StringTranslationTrait;

  /**
   * API Sync Basic Auth Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  public Config $config;

  /**
   * ApiSyncBasicAuthPlugin Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   Plugin id.
   * @param array $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Config\Config $config
   *   Plugin config settings.
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   *   When a scope provided to a service is invalid.
   */
  public function __construct(array $configuration, string $pluginId, array $pluginDefinition, Config $config) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      array $configuration,
      $pluginId,
      $pluginDefinition
  ): static {
    $configuration = array_merge(static::defaultConfiguration(), $configuration);
    return new static(
        $configuration,
        $pluginId,
        $pluginDefinition,
        $container->get('config.factory')->getEditable('apisync_basicauth.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration(): array {
    return [
      'login_user' => '',
      'login_password' => '',
      'instance_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {

    $form['login_user'] = [
      '#title' => $this->t('Username'),
      '#type' => 'textfield',
      '#description' => $this->t('Username'),
      '#required' => TRUE,
      '#default_value' => $this->config->get('login_user'),
    ];

    $form['login_password'] = [
      '#title' => 'Password',
      '#type' => 'password',
      '#required' => empty($this->config->get('login_password')),
      '#default_value' => $this->config->get('login_password'),
    ];

    $form['instance_url'] = [
      '#title' => $this->t('Instance URL'),
      '#type' => 'textfield',
      '#description' => $this->t('URL'),
      '#required' => TRUE,
      '#default_value' => $this->config->get('instance_url'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceUrl(): string {
    return $this->configuration['instance_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataUrl(): string {
    return rtrim($this->configuration['instance_url'], '/') . '/$metadata';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): TokenInterface {
    // @todo Separate interfaces so we don't need to force basic auth into
    // and oAuth abstraction.
    return new StdOAuth2Token(
      base64_encode($this->configuration['login_user'] . ':' . $this->configuration['login_password'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken(): void {
    // Noop.
    // For ApiSyncBasicAuthPlugin, the token is always made
    // out of user and password and thus can not be revoked.
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    $providerSettings = $formState->getValue('provider_settings');
    $this->config->set('login_user', $providerSettings['login_user']);
    $this->config->set('login_password', $providerSettings['login_password']);
    $this->config->set('instance_url', $providerSettings['instance_url']);
    $this->config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials(): ApiSyncCredentialsInterface {
    // Credentials are not required for basic auth ( so we probably have the
    // wrong abstraction here).
    return new ApiSyncCredentials('', '', '');
  }

}
