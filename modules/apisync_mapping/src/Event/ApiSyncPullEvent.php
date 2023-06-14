<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync\Event\ApiSyncBaseEvent;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * API Sync pull event.
 */
class ApiSyncPullEvent extends ApiSyncBaseEvent {

  /**
   * The mapping responsible for this pull.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * The mapped object associated with this pull.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   */
  protected ApiSyncMappedObjectInterface $mappedObject;

  /**
   * The Drupal entity into which the data is being pulled.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * The pull operation.
   *
   * One of:
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE.
   *
   * @var string
   */
  protected string $op;

  /**
   * TRUE or FALSE to indicate if pull is allowed for this event.
   *
   * @var bool
   */
  protected bool $pullAllowed;

  /**
   * Constructor for a ApiSyncPullEvent object.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   The mapped object.
   * @param string $op
   *   The operation. One of:
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE.
   */
  public function __construct(ApiSyncMappedObjectInterface $mappedObject, string $op) {
    $this->mappedObject = $mappedObject;
    $this->entity = $mappedObject->getMappedEntity();
    $this->mapping = $mappedObject->getMapping();
    $this->op = $op;
    $this->pullAllowed = TRUE;
  }

  /**
   * Getter.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  public function getEntity(): FieldableEntityInterface {
    return $this->entity;
  }

  /**
   * Getter.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   *   The mapping interface.
   */
  public function getMapping(): ApiSyncMappingInterface {
    return $this->mapping;
  }

  /**
   * Getter.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   The mapped object.
   */
  public function getMappedObject(): ApiSyncMappedObjectInterface {
    return $this->mappedObject;
  }

  /**
   * Getter for the pull operation.
   *
   * One of:
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE.
   *
   * @return string
   *   The op.
   */
  public function getOp(): string {
    return $this->op;
  }

  /**
   * Disallow and stop pull for the current queue item.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function disallowPull(): static {
    $this->pullAllowed = FALSE;
    return $this;
  }

  /**
   * Will return FALSE if any subscribers have called disallowPull().
   *
   * @return bool
   *   TRUE if pull is allowed, false otherwise.
   */
  public function isPullAllowed(): bool {
    return $this->pullAllowed;
  }

}
