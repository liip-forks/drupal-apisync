<?php

declare(strict_types=1);

namespace Drupal\apisync\Event;

/**
 * Interface for API Sync Exception events, primarily for logging.
 */
interface ApiSyncExceptionEventInterface {

  /**
   * Getter for exception.
   *
   * @return \Throwable|null
   *   The exception or NULL if no exception was given.
   */
  public function getException(): ?\Throwable;

  /**
   * Getter for log level.
   *
   * @return int|string
   *   Severity level for the event. Probably a Drupal\Core\Logger\RfcLogLevel
   *   or Psr\Log\LogLevel value.
   */
  public function getLevel(): int|string;

  /**
   * Getter for message string.
   *
   * @return string
   *   The message for this event, or a default message.
   */
  public function getMessage(): string;

  /**
   * Getter for message context.
   *
   * @return array
   *   The context aka args for this message, suitable for passing to ::log
   */
  public function getContext(): array;

}
