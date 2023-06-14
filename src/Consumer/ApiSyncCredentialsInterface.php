<?php

declare(strict_types = 1);

namespace Drupal\apisync\Consumer;

/**
 * API Sync credentials interface.
 */
interface ApiSyncCredentialsInterface {

  /**
   * Get the consumer key for these credentials.
   *
   * @return string
   *   The consumer key.
   */
  public function getConsumerKey(): string;

  /**
   * Get the login URL for these credentials.
   *
   * @return string
   *   The login url, e.g. https://login.apisync.com.
   */
  public function getLoginUrl(): string;

  /**
   * Sanity check for credentials validity.
   *
   * @return bool
   *   TRUE if credentials are set properly. Otherwise false.
   */
  public function isValid(): bool;

  /**
   * Create helper.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return static
   */
  public static function create(array $configuration): static;

}
