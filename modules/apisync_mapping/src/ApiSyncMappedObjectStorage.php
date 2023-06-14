<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Mapped Object Storage.
 *
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 */
class ApiSyncMappedObjectStorage extends SqlContentEntityStorage implements ApiSyncMappedObjectStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadByDrupal(string $entityTypeId, int|string $entityId): array {
    return $this->loadByProperties([
      'drupal_entity__target_type' => $entityTypeId,
      'drupal_entity__target_id' => $entityId,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByEntity(EntityInterface $entity): array {
    return $this->loadByProperties([
      'drupal_entity__target_type' => $entity->getEntityTypeId(),
      'drupal_entity__target_id' => $entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByEntityAndMapping(
      EntityInterface $entity,
      ApiSyncMappingInterface $mapping
  ): ?ApiSyncMappedObjectInterface {
    $result = $this->loadByProperties([
      'drupal_entity__target_type' => $entity->getEntityTypeId(),
      'drupal_entity__target_id' => $entity->id(),
      'apisync_mapping' => $mapping->id(),
    ]);
    return empty($result) ? NULL : reset($result);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByApiSyncId(string $apiSyncId): array {

    return $this->loadByProperties([
      'apisync_id' => $apiSyncId,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByApiSyncIdAndMapping(
      string $apiSyncId,
      ApiSyncMappingInterface $mapping
  ): ?ApiSyncMappedObjectInterface {
    $result = $this->loadByProperties([
      'apisync_id' => (string) $apiSyncId,
      'apisync_mapping' => $mapping->id(),
    ]);
    return empty($result) ? NULL : reset($result);
  }

  /**
   * {@inheritdoc}
   */
  public function setForcePull(ApiSyncMappingInterface $mapping): static {
    $this->database->update($this->baseTable)
      ->condition('apisync_mapping', $mapping->id())
      ->fields(['force_pull' => 1])
      ->execute();
    return $this;
  }

}
