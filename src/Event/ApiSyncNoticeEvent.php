<?php

declare(strict_types=1);

namespace Drupal\apisync\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Notice event.
 */
class ApiSyncNoticeEvent extends ApiSyncExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, string $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::NOTICE, $e, $message, $args);
  }

}
