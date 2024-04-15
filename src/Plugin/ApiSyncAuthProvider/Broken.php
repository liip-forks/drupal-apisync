<?php

declare(strict_types=1);

namespace Drupal\apisync\Plugin\ApiSyncAuthProvider;

use Drupal\apisync\ApiSyncAuthProviderPluginBase;
use Drupal\apisync\Consumer\ApiSyncCredentials;
use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\Core\Form\FormStateInterface;
use OAuth\OAuth2\Service\Exception\MissingRefreshTokenException;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * Fallback for broken / missing plugin.
 *
 * @Plugin(
 *   id = "broken",
 *   label = @Translation("Broken or missing provider"),
 *   credentials_class = "Drupal\apisync\Consumer\ApiSyncCredentials"
 * )
 */
class Broken extends ApiSyncAuthProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getCredentials(): ApiSyncCredentialsInterface {
    return new ApiSyncCredentials('', '', '');
  }

  /**
   * {@inheritdoc}
   */
  public function refreshAccessToken(TokenInterface $token): never {
    throw new MissingRefreshTokenException();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $this->messenger()->addError($this->t('Auth provider for %id is missing or broken.', ['%id' => $this->id]));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): TokenInterface {
    return new StdOAuth2Token();
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken(): void {

  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceUrl(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataUrl(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    $this->messenger()->addError($this->t('Auth provider for %id is missing or broken.', ['%id' => $this->id]));
  }

}
