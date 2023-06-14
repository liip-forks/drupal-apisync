<?php

declare(strict_types = 1);

namespace Drupal\apisync\Entity;

use Drupal\apisync\ApiSyncAuthProviderInterface;
use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Provides an interface defining an api sync auth config entity type.
 */
interface ApiSyncAuthConfigInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * ID getter.
   *
   * @return string|null
   *   The ID, or NULL if not defined.
   */
  public function id(): ?string;

  /**
   * Label getter.
   *
   * @return string|null
   *   The label, or NULL if not defined.
   */
  public function label(): ?string;

  /**
   * Plugin getter.
   *
   * @return \Drupal\apisync\ApiSyncAuthProviderInterface|null
   *   The auth provider plugin, or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getPlugin(): ?ApiSyncAuthProviderInterface;

  /**
   * Wrapper for provider settings to inject instance id, from auth config.
   *
   * @return array
   *   Provider settings.
   */
  public function getProviderSettings(): array;

  /**
   * Plugin ID getter.
   *
   * @return string|null
   *   The auth provider plugin id, or null.
   */
  public function getPluginId(): ?string;

  /**
   * Get credentials.
   *
   * @return \Drupal\apisync\Consumer\ApiSyncCredentialsInterface|false
   *   The credentials, or FALSE if plugin is unset.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getCredentials(): ApiSyncCredentialsInterface|false;

  /**
   * Auth manager wrapper.
   *
   * @return \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface
   *   The auth provider plugin manager.
   */
  public function authManager(): ApiSyncAuthProviderPluginManagerInterface;

  /**
   * Returns a list of plugins, for use in forms.
   *
   * @return array
   *   The list of plugins, indexed by ID.
   */
  public function getPluginsAsOptions(): array;

}
