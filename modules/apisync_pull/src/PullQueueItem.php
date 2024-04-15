<?php

declare(strict_types=1);

namespace Drupal\apisync_pull;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * A apisync_pull_queue item.
 */
class PullQueueItem {

  /**
   * The API Sync object data.
   *
   * @var \Drupal\apisync\OData\ODataObjectInterface
   */
  protected ODataObjectInterface $object;

  /**
   * The mapping id corresponding to this pull.
   *
   * @var string
   */
  protected string $mappingId;

  /**
   * Whether to force pull for the given record.
   *
   * @var bool
   */
  protected bool $forcePull;

  /**
   * Construct a pull queue item.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $object
   *   API Sync data.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param bool $forcePull
   *   Force data to be pulled, ignoring any timestamps.
   */
  public function __construct(
      ODataObjectInterface $object,
      ApiSyncMappingInterface $mapping,
      bool $forcePull = FALSE
  ) {
    $this->object = $object;
    $this->mappingId = $mapping->id;
    $this->forcePull = $forcePull;
  }

  /**
   * Getter.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface
   *   API Sync data.
   */
  public function getObject(): ODataObjectInterface {
    return $this->object;
  }

  /**
   * Getter.
   *
   * @return string
   *   API Sync mapping id.
   */
  public function getMappingId(): string {
    return $this->mappingId;
  }

  /**
   * Getter.
   *
   * @return bool
   *   Force pull.
   */
  public function getForcePull(): bool {
    return $this->forcePull;
  }

}
