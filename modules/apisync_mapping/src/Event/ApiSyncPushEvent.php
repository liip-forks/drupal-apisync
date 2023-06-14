<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync\Event\ApiSyncBaseEvent;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Push event.
 */
abstract class ApiSyncPushEvent extends ApiSyncBaseEvent {

  /**
   * The mapping.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * The mapped object.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   */
  protected ApiSyncMappedObjectInterface $mappedObject;

  /**
   * The Drupal entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * Constructor for a ApiSyncPushEvent object.
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

}
