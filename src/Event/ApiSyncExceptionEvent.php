<?php

declare(strict_types = 1);

namespace Drupal\apisync\Event;

/**
 * Base class for API Sync Exception events, primarily for logging.
 */
abstract class ApiSyncExceptionEvent extends ApiSyncBaseEvent implements ApiSyncExceptionEventInterface {

  /**
   * Exception.
   *
   * @var \Throwable|null
   */
  protected ?\Throwable $exception;

  /**
   * Message for logging.
   *
   * @var string
   */
  protected string $message;

  /**
   * Context, for t() translation.
   *
   * @var array
   */
  protected array $context;

  /**
   * Event level: notice, warning, or error.
   *
   * @var int|string
   */
  protected int|string $level;

  /**
   * Constructor for a ApiSyncExceptionEvent object.
   *
   * @param int|string $level
   *   Values are RfcLogLevel::NOTICE, RfcLogLevel::WARNING, RfcLogLevel::ERROR.
   * @param \Throwable|null $e
   *   A related Exception, if available.
   * @param string $message
   *   The translatable message string, suitable for t().
   * @param array $context
   *   Parameter array suitable for t(), to be translated for $message.
   */
  public function __construct(int|string $level, ?\Throwable $e = NULL, string $message = '', array $context = []) {
    $this->exception = $e;
    $this->level = $level;
    $this->message = $message;
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getException(): ?\Throwable {
    return $this->exception;
  }

  /**
   * {@inheritdoc}
   */
  public function getLevel(): int|string {
    return $this->level;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    if ($this->message) {
      return $this->message;
    }
    elseif ($this->exception && $this->exception->getMessage()) {
      return $this->exception->getMessage();
    }
    else {
      return 'Unknown API Sync event.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return $this->context;
  }

}
