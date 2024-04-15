<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * API Sync Mapping Storage.
 *
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 */
class ApiSyncMappingStorage extends ConfigEntityStorage {

  /**
   * Pass-through for loadByProperties()
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   *
   * @see loadByProperties()
   */
  public function loadByDrupal(string $entityTypeId): array {
    return $this->loadByProperties(["drupal_entity_type" => $entityTypeId]);
  }

  /**
   * Pass-through for loadByProperties() including bundle.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   *
   * @see loadByProperties()
   */
  public function loadByEntity(EntityInterface $entity): array {
    return $this->loadByProperties([
      'drupal_entity_type' => $entity->getEntityTypeId(),
      'drupal_bundle' => $entity->bundle(),
    ]);
  }

  /**
   * Return an array of ApiSyncMapping entities who are push-enabled.
   *
   * @param string $entityTypeId
   *   The entity type id. If given, filter the mappings by only this type.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The Mappings.
   *
   * @see loadPushMappingsByProperties()
   */
  public function loadPushMappings(string $entityTypeId = NULL): array {
    $properties = empty($entityTypeId)
      ? []
      : ["drupal_entity_type" => $entityTypeId];
    return $this->loadPushMappingsByProperties($properties);
  }

  /**
   * Get push Mappings to be processed during cron.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The Mappings to process.
   *
   * @see ::loadPushMappingsByProperties()
   */
  public function loadCronPushMappings(): array {
    if ($this->configFactory->get('apisync.settings')->get('standalone')) {
      return [];
    }
    $properties["push_standalone"] = FALSE;
    return $this->loadPushMappingsByProperties($properties);
  }

  /**
   * Get pull Mappings to be processed during cron.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The pull Mappings.
   *
   * @see loadPushMappingsByProperties()
   */
  public function loadCronPullMappings(): array {
    if ($this->configFactory->get('apisync.settings')->get('standalone')) {
      return [];
    }
    return $this->loadPullMappingsByProperties(["pull_standalone" => FALSE]);
  }

  /**
   * Return an array push-enabled mappings by properties.
   *
   * @param array $properties
   *   Properties array for storage handler.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The push mappings.
   *
   * @see loadByProperties()
   */
  public function loadPushMappingsByProperties(array $properties): array {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[] $mappings */
    $mappings = $this->loadByProperties($properties);
    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPush()) {
        continue;
      }
      $pushMappings[$key] = $mapping;
    }
    if (empty($pushMappings)) {
      return [];
    }
    return $pushMappings;
  }

  /**
   * Return an array push-enabled mappings by properties.
   *
   * @param array $properties
   *   Properties array for storage handler.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The pull mappings.
   *
   * @see loadByProperties()
   */
  public function loadPullMappingsByProperties(array $properties): array {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[] $mappings */
    $mappings = $this->loadByProperties($properties);
    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPull()) {
        continue;
      }
      $pushMappings[$key] = $mapping;
    }
    if (empty($pushMappings)) {
      return [];
    }
    return $pushMappings;
  }

  /**
   * Return an array of ApiSyncMapping entities who are pull-enabled.
   *
   * @param string $entityTypeId
   *   Optionally filter by entity type id.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The Mappings.
   *
   * @see loadByProperties()
   */
  public function loadPullMappings(string $entityTypeId = NULL): array {
    $pullMappings = [];
    $properties = empty($entityTypeId)
      ? []
      : ["drupal_entity_type" => $entityTypeId];
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[] $mappings */
    $mappings = $this->loadByProperties($properties);

    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPull()) {
        continue;
      }
      $pullMappings[$key] = $mapping;
    }
    if (empty($pullMappings)) {
      return [];
    }
    return $pullMappings;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []): array {
    // Build a query to fetch the entity IDs.
    $entityQuery = $this->getQuery();
    $this->buildPropertyQuery($entityQuery, $values);
    // Sort by the mapping weight to ensure entities/objects are processed in
    // the correct order.
    $entityQuery->sort('weight');
    $result = $entityQuery->execute();
    return $result ? $this->loadMultiple($result) : [];
  }

  /**
   * Return a unique list of mapped API Sync object types.
   *
   * @return array
   *   Mapped object types.
   *
   * @see loadByProperties()
   */
  public function getMappedObjectTypes(): array {
    $objectTypes = [];
    $mappings = $this->loadByProperties();
    foreach ($mappings as $mapping) {
      assert($mapping instanceof ApiSyncMappingInterface);
      $type = $mapping->getApiSyncObjectType();
      $objectTypes[$type] = $type;
    }
    return $objectTypes;
  }

}
