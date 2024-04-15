<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\Exception\ConfigurationException;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Provides the API Sync ID.
 */
class ApiSyncIdProvider implements ApiSyncIdProviderInterface {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\apisync\Exception\ConfigurationException
   */
  public function getApiSyncId(ODataObjectInterface $oDataRecord, ApiSyncMappingInterface $mapping): string {
    $configEntity = $mapping->getRelatedApiSyncMappedObjectType();
    if ($configEntity === NULL) {
      throw new \Exception('No mapped object type found for mapping ' . $mapping->id());
    }
    $keyMappings = $configEntity->getKeyFieldMappings();
    if (!$configEntity->apiSyncIdShallBeHashed()) {
      // We know there can only be one key if not hashed.
      $keyMapping = current($keyMappings);
      $this->assertFieldExists($keyMapping['apisync_field'], $oDataRecord);
      return (string) $oDataRecord->field($keyMapping['apisync_field']);
    }
    return $this->buildHashedId($keyMappings, $oDataRecord);
  }

  /**
   * Build the hashed API Sync ID.
   *
   * @param array $keyMappings
   *   Key mappings.
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   OData record.
   *
   * @return string
   *   Hashed API Sync ID.
   */
  protected function buildHashedId(array $keyMappings, ODataObjectInterface $oDataRecord): string {
    $keyConcatenated = '';
    foreach ($keyMappings as $keyMapping) {
      $this->assertFieldExists($keyMapping['apisync_field'], $oDataRecord);
      $keyConcatenated .= $oDataRecord->field($keyMapping['apisync_field']);
    }
    // Hashed to ensure fixed length of 128 bit (32 char).
    return md5($keyConcatenated);
  }

  /**
   * Assert the apisync field exists on the odata record.
   *
   * @param string $apisyncField
   *   API Sync Field.
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   OData record.
   */
  protected function assertFieldExists(string $apisyncField, ODataObjectInterface $oDataRecord): void {
    if (!$oDataRecord->hasField($apisyncField)) {
      throw new ConfigurationException(
        sprintf('The apisync_field %s was not found on the oData record provided.', $apisyncField)
      );
    }
  }

}
