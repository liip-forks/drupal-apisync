<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\apisync\OData\SelectQueryInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Mapping between Drupal and API Sync records.
 */
interface ApiSyncMappingInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Magic getter method for mapping properties.
   *
   * @param string $key
   *   The property to get.
   *
   * @return mixed
   *   The value.
   */
  public function __get(string $key): mixed;

  /**
   * Get the related API sync mapped object type config entity.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface|null
   *   API sync mapped object type config entity.
   */
  public function getRelatedApiSyncMappedObjectType(): ?ApiSyncMappedObjectTypeInterface;

  /**
   * Get all the mapped field plugins for this mapping.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface[]
   *   The fields.
   */
  public function getFieldMappings(): array;

  /**
   * Given a field config, create an instance of a field mapping.
   *
   * @param array $field
   *   Field plugin definition. Keys are "drupal_field_type" and "config".
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface
   *   The field.
   */
  public function getFieldMapping(array $field): ApiSyncMappingFieldPluginInterface;

  /**
   * Get the API Sync Object type name for this mapping, e.g. "Contact".
   *
   * @return string
   *   The object name.
   */
  public function getApiSyncObjectType(): string;

  /**
   * Get the Drupal entity type name for this mapping, e.g. "node".
   *
   * @return string
   *   The entity type id.
   */
  public function getDrupalEntityType(): string;

  /**
   * Get the Drupal bundle name for this mapping, e.g. "article".
   *
   * @return string
   *   The bundle.
   */
  public function getDrupalBundle(): string;

  /**
   * Get all the field plugins which are configured to pull from remote.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface[]
   *   Array of objects.
   */
  public function getPullFields(): array;

  /**
   * Get a flat array of the field plugins which are configured to pull.
   *
   * @return array
   *   Keys and values are API Sync field names.
   */
  public function getPullFieldsArray(): array;

  /**
   * The API Sync date field which determines whether to pull.
   *
   * @return string
   *   API Sync field name.
   */
  public function getPullTriggerDate(): string;

  /**
   * Getter for push_standalone property.
   *
   * @return bool
   *   TRUE if this mapping is set to process push queue via a standalone
   *   endpoint instead of during cron.
   */
  public function doesPushStandalone(): bool;

  /**
   * Getter for push_standalone property.
   *
   * @return bool
   *   TRUE if this mapping is set to process push queue via a standalone
   *   endpoint instead of during cron.
   */
  public function doesPullStandalone(): bool;

  /**
   * Checks mappings for any push operation.
   *
   * @return bool
   *   TRUE if this mapping is configured to push.
   */
  public function doesPush(): bool;

  /**
   * Checks mappings for any pull operation.
   *
   * @return bool
   *   TRUE if this mapping is configured to pull.
   */
  public function doesPull(): bool;

  /**
   * Checks if mapping has any of the given triggers.
   *
   * @param array $triggers
   *   Collection of apisync_mapping_SYNC_* constants from MappingConstants.
   *
   * @see \Drupal\apisync_mapping\MappingConstants
   *
   * @return bool
   *   TRUE if any of the given $triggers are enabled for this mapping.
   */
  public function checkTriggers(array $triggers): bool;

  /**
   * Return TRUE if an upsert key is set for this mapping.
   *
   * @return bool
   *   Return TRUE if an upsert key is set for this mapping.
   */
  public function hasKey(): bool;

  /**
   * Return name of the API Sync field which is the upsert key.
   *
   * @return string
   *   The upsert key API Sync field name.
   */
  public function getKeyField(): string;

  /**
   * Given a Drupal entity, get the value to be upserted.
   *
   * @return mixed
   *   The upsert field value.
   */
  public function getKeyValue(EntityInterface $entity): mixed;

  /**
   * Return the timestamp for the date of most recent delete processing.
   *
   * @return int|null
   *   Integer timestamp of last delete, or NULL if delete has not been run.
   */
  public function getLastDeleteTime(): ?int;

  /**
   * Set this mapping as having been last processed for deletes at $time.
   *
   * @param int|null $time
   *   The delete time to set.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setLastDeleteTime(?int $time): static;

  /**
   * Return the timestamp for the date of most recent pull processing.
   *
   * @return int|null
   *   Integer timestamp of last pull, or NULL if pull has not been run.
   */
  public function getLastPullTime(): ?int;

  /**
   * Set this mapping as having been last pulled at $time.
   *
   * @param int|null $time
   *   The pull time to set.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setLastPullTime(?int $time): static;

  /**
   * Get the timestamp when the next pull should be processed for this mapping.
   *
   * @return int
   *   The next pull time.
   */
  public function getNextPullTime(): int;

  /**
   * Generate a select query to pull records from remote for this mapping.
   *
   * @param array $mappedFields
   *   Fetch only these fields, if given, otherwise fetch all mapped fields.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime()
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use
   *   "now".
   *
   * @return \Drupal\apisync\OData\SelectQueryInterface
   *   The pull query.
   */
  public function getPullQuery(array $mappedFields = [], int $start = 0, int $stop = 0): SelectQueryInterface;

  /**
   * Returns a timstamp when the push queue was last processed for this mapping.
   *
   * @return int|null
   *   The last push time, or NULL.
   */
  public function getLastPushTime(): ?int;

  /**
   * Set the timestamp when the push queue was last process for this mapping.
   *
   * @param int $time
   *   The push time to set.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setLastPushTime(int $time): static;

  /**
   * Get the timestamp when the next push should be processed for this mapping.
   *
   * @return int
   *   The next push time.
   */
  public function getNextPushTime(): int;

  /**
   * Return TRUE if this mapping should always use upsert over create or update.
   *
   * @return bool
   *   Whether to upsert, ignoring any local API Sync ID.
   */
  public function alwaysUpsert(): bool;

}
