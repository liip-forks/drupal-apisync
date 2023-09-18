<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * ApiSyncMappedObject Factory.
 */
class ApiSyncMappedObjectFactory implements ApiSyncMappedObjectFactoryInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * API Sync ID provider.
   *
   * @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface
   */
  protected ApiSyncIdProviderInterface $apiSyncIdProvider;

  /**
   * Mapped object storage.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Construct a new ApiSyncMappedObjectFactory.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apiSyncIdProvider
   *   API Sync ID provider.
   */
  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncIdProviderInterface $apiSyncIdProvider
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->apiSyncIdProvider = $apiSyncIdProvider;
  }

  /**
   * Get the mapped object type from a mapping.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface
   *   The related mapped object type.
   *
   * @throws \Exception
   *   No related mapped object was found.
   */
  protected function getApiSyncMappedObjectType(ApiSyncMappingInterface $mapping): ApiSyncMappedObjectTypeInterface {
    $mappedObjectType = $mapping->getRelatedApiSyncMappedObjectType();
    if ($mappedObjectType === NULL) {
      throw new \Exception('No mapped object type found for mapping ' . $mapping->id());
    }
    return $mappedObjectType;
  }

  /**
   * Create an instance.
   *
   * This method differs from the create static factory method on the
   * ApiSyncMappedObject entity in that it ensures that an instance
   * of the correct bundle is created using a valid API sync key.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   OData record.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   The mapped object.
   */
  public function createFromRecord(
      ApiSyncMappingInterface $mapping,
      ODataObjectInterface $oDataRecord,
  ): ApiSyncMappedObjectInterface {
    $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping);

    return $this->mappedObjectStorage()->create([
      'type' => $this->getApiSyncMappedObjectType($mapping)->id(),
      'drupal_entity' => [
        'target_type' => $mapping->getDrupalEntityType(),
      ],
      'apisync_mapping' => $mapping->id(),
      'apisync_id' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
    ]);

  }

  /**
   * Create mapped object.
   *
   * Note: The created mapped objected cannot be saved until the apisync_id
   * is added as the mapping and the apisync_id form a composite unique key.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param string|null $apiSyncId
   *   API Sync ID.
   * @param string|int|null $targetId
   *   Target entity ID.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   Mapped object.
   */
  public function create(
      ApiSyncMappingInterface $mapping,
      string|null $apiSyncId,
      string|int|null $targetId
  ): ApiSyncMappedObjectInterface {
    $data = [
      'type' => $this->getApiSyncMappedObjectType($mapping)->id(),
      'drupal_entity' => [
        'target_type' => $mapping->getDrupalEntityType(),
      ],
      'apisync_mapping' => $mapping->id(),
    ];

    if ($apiSyncId) {
      $data['apisync_id'] = $apiSyncId;
    }

    if ($targetId !== NULL) {
      $data['drupal_entity']['target_id'] = $targetId;
    }

    return $this->mappedObjectStorage()->create($data);

  }

  /**
   * Get the mapped object storage.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   *   Mapped object storage.
   */
  protected function mappedObjectStorage(): ApiSyncMappedObjectStorageInterface {
    if (!isset($this->mappedObjectStorage)) {
      $this->mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    }
    return $this->mappedObjectStorage;
  }

}
