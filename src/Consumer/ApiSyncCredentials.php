<?php

declare(strict_types=1);

namespace Drupal\apisync\Consumer;

use OAuth\Common\Consumer\Credentials;

/**
 * Stub class ApiSyncCredentials. Used for broken / fallback plugin only.
 */
class ApiSyncCredentials extends Credentials implements ApiSyncCredentialsInterface {

  /**
   * Login URL e.g. https://test.apisync.com or https://login.apisync.com.
   *
   * @var string
   */
  protected string $loginUrl;

  /**
   * Consumer key for the API Sync connected OAuth app.
   *
   * @var string
   */
  protected string $consumerKey;

  /**
   * {@inheritdoc}
   */
  public function getConsumerKey(): string {
    return $this->consumerKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl(): string {
    return $this->loginUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(): bool {
    // This class is a stub.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $configuration): static {
    return new static($configuration['consumer_key'], $configuration['consumer_secret'], NULL);
  }

}
