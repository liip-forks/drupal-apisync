<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync\Event\ApiSyncBaseEvent;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Pull entity event.
 */
class ApiSyncPullEntityValueEvent extends ApiSyncBaseEvent {

  /**
   * The value of the field to be assigned.
   *
   * @var mixed
   */
  protected mixed $entityValue;

  /**
   * The field plugin responsible for pulling the data.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface
   */
  protected ApiSyncMappingFieldPluginInterface $fieldPlugin;

  /**
   * The mapped object, or mapped object stub.
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
   * The Drupal entity, or entity stub.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Constructor for a ApiSyncPullEntityValueEvent object.
   *
   * @param mixed $value
   *   The value to be assigned.
   * @param \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface $fieldPlugin
   *   The field plugin.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   The mapped object.
   */
  public function __construct(
      &$value,
      ApiSyncMappingFieldPluginInterface $fieldPlugin,
      ApiSyncMappedObjectInterface $mappedObject
  ) {
    $this->entityValue = $value;
    $this->fieldPlugin = $fieldPlugin;
    $this->mappedObject = $mappedObject;
    $this->entity = $mappedObject->getMappedEntity();
    $this->mapping = $mappedObject->getMapping();
  }

  /**
   * Entity value getter.
   *
   * @return mixed
   *   The value to be pulled and assigned to the Drupal entity.
   */
  public function getEntityValue(): mixed {
    return $this->entityValue;
  }

  /**
   * Field plugin getter.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface
   *   The field plugin.
   */
  public function getFieldPlugin(): ApiSyncMappingFieldPluginInterface {
    return $this->fieldPlugin;
  }

  /**
   * Entity getter.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity(): EntityInterface {
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
