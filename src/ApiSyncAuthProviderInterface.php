<?php

declare(strict_types = 1);

namespace Drupal\apisync;

use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * API Sync Auth Provider Interface.
 */
interface ApiSyncAuthProviderInterface extends PluginFormInterface, ContainerFactoryPluginInterface, PluginInspectionInterface {

  /**
   * ID of this service.
   *
   * @return string
   *   ID of this service.
   */
  public function id(): string;

  /**
   * Label of this service.
   *
   * @return string
   *   Id of this service.
   */
  public function label(): string;

  /**
   * Retrieve the access token.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface
   *   The access token.
   */
  public function getAccessToken(): TokenInterface;

  /**
   * Check if plugin has acces token.
   *
   * @return bool
   *   TRUE if plugin has a valid access token.
   */
  public function hasAccessToken(): bool;

  /**
   * Revoke the current access token.
   */
  public function revokeAccessToken(): void;

  /**
   * Retrieve the instance URL from the plugin configuration.
   *
   * @return string
   *   The instance URL.
   */
  public function getInstanceUrl(): string;

  /**
   * Retrieve the metadata URL from the plugin configuration.
   *
   * @return string
   *   The metadata URL.
   */
  public function getMetadataUrl(): string;

  /**
   * Form submission handler for the 'save' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function save(array $form, FormStateInterface $formState): void;

  /**
   * Get the auth credentials.
   *
   * @return \Drupal\apisync\Consumer\ApiSyncCredentialsInterface
   *   The auth credentials.
   */
  public function getCredentials(): ApiSyncCredentialsInterface;

}
