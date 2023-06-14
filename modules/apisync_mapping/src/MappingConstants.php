<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

/**
 * Defines events for API Sync.
 *
 * @see \Drupal\apisync\Event\ApiSyncEvent
 */
final class MappingConstants {
  /**
   * Define when a data sync should take place for a given mapping.
   */
  public const APISYNC_MAPPING_SYNC_DRUPAL_CREATE = 'push_create';
  public const APISYNC_MAPPING_SYNC_DRUPAL_UPDATE = 'push_update';
  public const APISYNC_MAPPING_SYNC_DRUPAL_DELETE = 'push_delete';
  public const APISYNC_MAPPING_SYNC_REMOTE_CREATE = 'pull_create';
  public const APISYNC_MAPPING_SYNC_REMOTE_UPDATE = 'pull_update';
  public const APISYNC_MAPPING_SYNC_REMOTE_DELETE = 'pull_delete';
  public const APISYNC_MAPPING_TRIGGER_MAX_LENGTH = 16;

  /**
   * Field mapping direction constants.
   */
  public const APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE = 'drupal_remote';
  public const APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL = 'remote_drupal';
  public const APISYNC_MAPPING_DIRECTION_SYNC = 'sync';

  /**
   * Delimiter used in API Sync multipicklists.
   */
  public const APISYNC_MAPPING_ARRAY_DELIMITER = ';';

  /**
   * Field mapping maximum name length.
   */
  public const APISYNC_MAPPING_NAME_LENGTH = 128;


  public const APISYNC_MAPPING_STATUS_SUCCESS = 1;
  public const APISYNC_MAPPING_STATUS_ERROR = 0;

}
