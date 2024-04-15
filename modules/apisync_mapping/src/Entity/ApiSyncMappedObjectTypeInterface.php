<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an api sync mapped object type entity type.
 */
interface ApiSyncMappedObjectTypeInterface extends ConfigEntityInterface {

  /**
   * Get field mappings.
   *
   * @return array
   *   Field mappings.
   */
  public function getFieldMappings(): array;

  /**
   * Get key field mappings.
   *
   * @return array
   *   Field mappings that are keys.
   */
  public function getKeyFieldMappings(): array;

  /**
   * Get field mappings that are not keys.
   *
   * @return array
   *   Field mappings that are not keys.
   */
  public function getNonKeyFieldMappings(): array;

  /**
   * Get violations relating to field mappings.
   *
   * @return array
   *   Array of violations.
   */
  public function getFieldMappingViolations(): array;

  /**
   * Check if the apisync_id shall be hashed.
   *
   * The key will be hashed any time we don't have a key mapped directly
   * to the apisync_id field. We can't map a remote field  to this drupal field
   * if we have a composite key. Further, values mapped directly cannot be
   * hashed. This allows for single keys to also be hashed if needed
   * due to the length of the key exceeding the apisync_id field length.
   *
   * @return bool
   *   The apisync_id shall be hashed.
   */
  public function apiSyncIdShallBeHashed(): bool;

}
