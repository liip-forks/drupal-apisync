<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * ApiSyncMappedObject Factory.
 */
interface ApiSyncMappedObjectFactoryInterface {

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
      ODataObjectInterface $oDataRecord
  ): ApiSyncMappedObjectInterface;

  /**
   * Create mapped object.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param string|null $apiSyncId
   *   API Sync ID.
   * @param string|int|null $targetEntityId
   *   Target entity ID.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   Mapped object or null if mapping needs a hashed API sync ID.
   */
  public function create(
      ApiSyncMappingInterface $mapping,
      ?string $apiSyncId,
      string|int|null $targetEntityId
  ): ApiSyncMappedObjectInterface;

}
