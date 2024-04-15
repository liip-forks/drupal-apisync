<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * API Sync Mapped Object Type Storage.
 *
 * Extends ConfigEntityStorage to allow convenience wrappers to be added in
 * future without needing hooks to update storage.
 */
class ApiSyncMappedObjectTypeStorage extends ConfigEntityStorage {

}
