<?php

declare(strict_types = 1);

namespace Drupal\apisync;

use Drupal\apisync\Entity\ApiSyncAuthConfigInterface;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * Auth provider plugin manager interface.
 */
interface ApiSyncAuthProviderPluginManagerInterface extends PluginManagerInterface, FallbackPluginManagerInterface {

  /**
   * Get a list of all the auth providers.
   *
   * @return \Drupal\apisync\Entity\ApiSyncAuthConfigInterface[]
   *   An array of all providers in the auth storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProviders(): array;

  /**
   * Check if there are any auth providers in the auth storage.
   *
   * @return bool
   *   TRUE if the auth storage contains providers.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasProviders(): bool;

  /**
   * Get the auth config entity.
   *
   * @return \Drupal\apisync\Entity\ApiSyncAuthConfigInterface|null
   *   The auth config, or NULL.
   */
  public function getConfig(): ?ApiSyncAuthConfigInterface;

  /**
   * Get the configured auth provider plugin.
   *
   * @return \Drupal\apisync\ApiSyncAuthProviderInterface|null
   *   The auth provider plugin, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getProvider(): ?ApiSyncAuthProviderInterface;

  /**
   * Retrieve an OAuth token form the configured plugin.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface|null
   *   The OAuth token, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getToken(): ?TokenInterface;

  /**
   * Refresh the OAuth token.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface|null
   *   The OAuth token, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function refreshToken(): ?TokenInterface;

}
