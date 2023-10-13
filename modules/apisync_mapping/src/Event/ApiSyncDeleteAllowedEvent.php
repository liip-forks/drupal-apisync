<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync\Event\ApiSyncBaseEvent;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Delete allowed event.
 */
class ApiSyncDeleteAllowedEvent extends ApiSyncBaseEvent {

  /**
   * Indicates whether delete is prohibited.
   *
   * @var bool
   */
  protected bool $deleteProhibited = FALSE;

  /**
   * The mapped object.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   */
  protected ApiSyncMappedObjectInterface $mappedObject;

  /**
   * The mapping responsible for this pull.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * The Drupal entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * ApiSyncDeleteAllowedEvent dispatched before deleting an entity.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   The mapped object.
   */
  public function __construct(ApiSyncMappedObjectInterface $mappedObject) {
    $this->mappedObject = $mappedObject;
    $this->entity = ($mappedObject) ? $mappedObject->getMappedEntity() : NULL;
    $this->mapping = ($mappedObject) ? $mappedObject->getMapping() : NULL;
  }

  /**
   * Entity getter.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  public function getEntity(): FieldableEntityInterface {
    return $this->entity;
  }

  /**
   * Mapping getter.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   *   The mapping.
   */
  public function getMapping(): ApiSyncMappingInterface {
    return $this->mapping;
  }

  /**
   * Mapped object getter.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   The mapped object.
   */
  public function getMappedObject(): ApiSyncMappedObjectInterface {
    return $this->mappedObject;
  }

  /**
   * Returns FALSE if delete is disallowed.
   *
   * Note: a subscriber cannot "force" a delete when any other subscriber has
   * disallowed it.
   *
   * @return false|null
   *   Returns FALSE if DELETE_ALLOWED event has been fired, and any subscriber
   *   wants to prevent delete. Otherwise, returns NULL.
   */
  public function isDeleteProhibited(): ?bool {
    return $this->deleteProhibited;
  }

  /**
   * Stop API Sync record from being deleted.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function disallowDelete(): static {
    $this->deleteProhibited = TRUE;
    return $this;
  }

}
