<?php

declare(strict_types = 1);

namespace Drupal\apisync_push;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Queue\ReliableQueueInterface;

/**
 * Push queue interface.
 */
interface PushQueueInterface extends ReliableQueueInterface {

  /**
   * Claim up to $n items from the current queue.
   *
   * If queue is empty, return an empty array.
   *
   * @param int $n
   *   Number of items to claim.
   * @param int $failLimit
   *   Do not claim items with this many or more failures.
   * @param int $leaseTime
   *   Time, in seconds, for which to hold this claim.
   *
   * @see DatabaseQueue::claimItem
   *
   * @return array
   *   Zero to $n Items indexed by item_id
   */
  public function claimItems(int $n, int $failLimit = 0, int $leaseTime = 0): array;

  /**
   * Inherited classes MUST throw an exception when this method is called.
   *
   * Use claimItems() instead.
   *
   * @param int $leaseTime
   *   How long should the item remain claimed until considered released?
   *
   * @throws \Exception
   *   Whenever called.
   */
  public function claimItem($leaseTime = NULL): void;

  /**
   * Failed item handler.
   *
   * Exception handler so that Queue Processors don't have to worry about what
   * happens when a queue item fails.
   *
   * @param \Throwable $e
   *   The exception which caused the failure.
   * @param object $item
   *   The failed item.
   */
  public function failItem(\Throwable $e, \stdClass $item): void;

  /**
   * Given an API Sync mapping, process all its push queue entries.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync mapping.
   *
   * @return int
   *   The number of items procesed, or -1 if there was any error, And also
   *   dispatches a ApiSyncEvents::ERROR event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function processQueue(ApiSyncMappingInterface $mapping): int;

  /**
   * Process API Sync queues.
   *
   * @param array $mappings
   *   The mappings.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function processQueues(array $mappings = []): static;

}
