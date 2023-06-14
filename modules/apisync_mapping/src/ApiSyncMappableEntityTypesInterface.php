<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Mappable entity types interface.
 */
interface ApiSyncMappableEntityTypesInterface {

  /**
   * Get an array of entity types that are mappable.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   Objects which are exposed for mapping to API Sync.
   */
  public function getMappableEntityTypes(): array;

  /**
   * Given an entity type, return true or false to indicate if it's mappable.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return bool
   *   TRUE or FALSE to indicate if the given entity type is mappable.
   */
  public function isMappable(EntityTypeInterface $entityType): bool;

}
