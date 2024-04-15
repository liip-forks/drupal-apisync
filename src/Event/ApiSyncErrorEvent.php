<?php

declare(strict_types=1);

namespace Drupal\apisync\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Error event.
 */
class ApiSyncErrorEvent extends ApiSyncExceptionEvent {

  /**
   * A basic error message using Error::decodeException as arguments.
   *
   * @var string
   */
  const BASE_ERROR_MESSAGE = '%type: @message in %function (line %line of %file).';

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, string $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::ERROR, $e, $message, $args);
  }

}
