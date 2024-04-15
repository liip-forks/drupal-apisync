<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Provides a list of mapped object IDs to delete.
 */
interface ApiSyncDeleteProviderInterface {

  /**
   * Get mapped objects IDs to delete.
   *
   * Here we get all the mapped object IDs for the mapping from the database.
   * We then query the remote endpoint for the mapping. We then create a list
   * of IDs that are present locally, but not on the remote.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return array
   *   An array of mapped object IDs to delete.
   */
  public function getMappedObjectIdsToDelete(ApiSyncMappingInterface $mapping): array;

}
