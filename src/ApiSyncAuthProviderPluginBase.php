<?php

declare(strict_types=1);

namespace Drupal\apisync;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared methods for auth providers.
 */
abstract class ApiSyncAuthProviderPluginBase implements ApiSyncAuthProviderInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * Configuration.
   *
   * @var array
   */
  protected array $configuration;

  /**
   * Provider id, e.g. jwt, oauth.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Plugin definition.
   *
   * @var array
   */
  protected array $pluginDefinition;

  /**
   * Instance id, e.g. "sandbox1" or "production".
   *
   * @var string|null
   */
  protected ?string $id;

  /**
   * Constructor for a ApiSyncAuthProviderPluginBase object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $pluginId
   *   Plugin id.
   * @param array $pluginDefinition
   *   Plugin definition.
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   *   When a scope provided to a service is invalid.
   */
  public function __construct(array $configuration, string $pluginId, array $pluginDefinition) {
    $this->id = !empty($configuration['id']) ? $configuration['id'] : NULL;
    $this->configuration = $configuration;
    $this->pluginId = $pluginId;
    $this->pluginDefinition = $pluginDefinition;
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
    return new static($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * The default configuration array of the plugin.
   *
   * @return array
   *   The default configuration.
   */
  public static function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    return $this->pluginDefinition;
  }

  /**
   * Retrieve a specific key of the configuration array.
   *
   * @param string|null $key
   *   The key.
   *
   * @return array|mixed
   *   Specifc configuration entry if $key is set, or configuration array.
   */
  public function getConfiguration(?string $key = NULL): mixed {
    if ($key !== NULL) {
      return !empty($this->configuration[$key]) ? $this->configuration[$key] : NULL;
    }
    return $this->configuration;
  }

  /**
   * Configuration setter.
   *
   * @param array $configuration
   *   The new configuration array.
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    $this->setConfiguration($formState->getValue('provider_settings'));
  }

}
