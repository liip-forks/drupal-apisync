<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Provides the API Sync ID.
 */
interface ApiSyncIdProviderInterface {

  /**
   * Gets the API Sync ID for an oData record for a given mapping.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   OData Record.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   *
   * @return string
   *   API Sync ID.
   */
  public function getApiSyncId(ODataObjectInterface $oDataRecord, ApiSyncMappingInterface $mapping): string;

}
