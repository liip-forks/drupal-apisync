<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Mappable entity types constructor.
 */
class ApiSyncMappableEntityTypes implements ApiSyncMappableEntityTypesInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new ApiSyncMappableEntityTypes object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappableEntityTypes(): array {
    $entityInfo = $this->entityTypeManager->getDefinitions();
    $mappable = [];

    // We're only concerned with fieldable entities. This is a relatively
    // arbitrary restriction, but otherwise there would be an unweildy number
    // of entities. Also exclude ApiSyncMappedObjects themselves.
    foreach ($entityInfo as $entityTypeId => $entityType) {
      if ($this->isMappable($entityType)) {
        $mappable[$entityTypeId] = $entityType;
      }
    }
    return $mappable;
  }

  /**
   * {@inheritdoc}
   */
  public function isMappable(EntityTypeInterface $entityType): bool {
    if (in_array('Drupal\Core\Entity\ContentEntityTypeInterface', class_implements($entityType))
        && $entityType->id() != 'apisync_mapped_object'
    ) {
      return TRUE;
    }
    return FALSE;
  }

}
