<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

/**
 * Push allowed event.
 */
class ApiSyncPushAllowedEvent extends ApiSyncPushOpEvent {

  /**
   * Indicates whether push is allowed to continue.
   *
   * @var bool|null
   */
  protected ?bool $pushAllowed = NULL;

  /**
   * Returns FALSE if push is disallowed.
   *
   * Note: a subscriber cannot "force" a push when any other subscriber has
   * disallowed it.
   *
   * @return false|null
   *   Returns FALSE if PUSH_ALLOWED event has been fired, and any subscriber
   *   wants to prevent push. Otherwise, returns NULL.
   */
  public function isPushAllowed(): ?bool {
    return $this->pushAllowed === FALSE ? FALSE : NULL;
  }

  /**
   * Stop API Sync record from being pushed.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function disallowPush(): static {
    $this->pushAllowed = FALSE;
    return $this;
  }

}
