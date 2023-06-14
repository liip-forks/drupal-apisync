<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Mapped Object Storage Interface.
 */
interface ApiSyncMappedObjectStorageInterface extends SqlEntityStorageInterface, ContentEntityStorageInterface {

  /**
   * Load ApiSyncMappedObjects by entity type id and entity id.
   *
   * @param string $entityTypeId
   *   Entity type id.
   * @param int|string $entityId
   *   Entity id.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface[]
   *   Mapped objects.
   *
   * @see loadByProperties()
   */
  public function loadByDrupal(string $entityTypeId, int|string $entityId): array;

  /**
   * Load ApiSyncMappedObjects by Drupal Entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface[]
   *   Mapped objects.
   *
   * @see loadByProperties()
   */
  public function loadByEntity(EntityInterface $entity): array;

  /**
   * Load a single ApiSyncMappedObject by Drupal Entity and Mapping.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync Mapping.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface|null
   *   The matching Mapped Object, or null if none are found.
   *
   * @see loadByProperties()
   */
  public function loadByEntityAndMapping(
      EntityInterface $entity,
      ApiSyncMappingInterface $mapping
  ): ?ApiSyncMappedObjectInterface;

  /**
   * Load ApiSyncMappedObjects by API Sync ID.
   *
   * @param string $apiSyncId
   *   API Sync ID.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface[]
   *   Matching mapped objects.
   *
   * @see loadByProperties()
   */
  public function loadByApiSyncId(string $apiSyncId): array;

  /**
   * Load a single ApiSyncMappedObject by Mapping and API Sync ID.
   *
   * @param string $apiSyncId
   *   API Sync id.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync mapping.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface|null
   *   Mapped object, or null if none are found.
   *
   * @see loadByProperties()
   */
  public function loadByApiSyncIdAndMapping(
      string $apiSyncId,
      ApiSyncMappingInterface $mapping
  ): ?ApiSyncMappedObjectInterface;

  /**
   * Set "force_pull" column to TRUE for mapped objects of the given mapping.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setForcePull(ApiSyncMappingInterface $mapping): static;

}
