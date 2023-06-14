<?php

declare(strict_types = 1);

namespace Drupal\apisync\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Warning event.
 */
class ApiSyncWarningEvent extends ApiSyncExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, string $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::WARNING, $e, $message, $args);
  }

}
