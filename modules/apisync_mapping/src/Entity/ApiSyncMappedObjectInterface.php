<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\PushActions;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Mapped Object interface.
 */
interface ApiSyncMappedObjectInterface extends EntityChangedInterface, RevisionLogInterface, ContentEntityInterface {

  /**
   * Get the attached mapping entity.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   *   The mapping entity.
   */
  public function getMapping(): ApiSyncMappingInterface;

  /**
   * Get the mapped Drupal entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null The mapped Drupal entity.
   *   The mapped Drupal entity.
   */
  public function getMappedEntity(): ?EntityInterface;

  /**
   * Return a numeric timestamp for comparing to API Sync record timestamp.
   *
   * @return int
   *   The entity_updated value from the Mapped Object.
   */
  public function getChanged(): int;

  /**
   * Wrapper for apisync.odata_client service.
   *
   * @return \Drupal\apisync\OData\ODataClientInterface
   *   The service.
   */
  public function client(): ODataClientInterface;

  /**
   * Wrapper for Drupal core event_dispatcher service.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   Event dispatcher.
   */
  public function eventDispatcher(): EventDispatcherInterface;

  /**
   * Wrapper for config getter.
   *
   * @param mixed $name
   *   The name of the config to get.
   *
   * @return mixed
   *   The config value.
   */
  public function config(mixed $name): mixed;

  /**
   * Wrapper for API Sync auth provider plugin manager.
   *
   * @return \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface
   *   The auth provider plugin manager.
   */
  public function authManager(): ApiSyncAuthProviderPluginManagerInterface;

  /**
   * Get an API sync url for the linked record.
   *
   * @return string
   *   The API sync url for the linked API Sync record.
   */
  public function getApiSyncUrl(): string;

  /**
   * Gets the relative path for the linked record.
   *
   * @return string
   *   The API Sync relative path for the linked API Sync record.
   */
  public function getApiSyncPath(): string;

  /**
   * Attach a Drupal entity to the mapped object.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   The entity to be attached.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setDrupalEntityStub(?FieldableEntityInterface $entity = NULL): static;

  /**
   * Wrapper for drupalEntityStub.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   The mapped entity.
   */
  public function getDrupalEntityStub(): ?FieldableEntityInterface;

  /**
   * Get the mapped API Sync record, only available during pull.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface|null
   *   The data, available only during pull.
   */
  public function getApiSyncRecord(): ?ODataObjectInterface;

  /**
   * Getter for apisync_id.
   *
   * @return string|null
   *   API Sync ID, or NULL if not synced.
   */
  public function apiSyncId(): ?string;

  /**
   * Push data to remote.
   *
   * @return mixed
   *   API Sync ID or NULL depending on result from remote.
   */
  public function push(): mixed;

  /**
   * Delete the mapped API Sync object in remote.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function pushDelete(): static;

  /**
   * Set a Drupal entity for this mapped object.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   Entity.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setDrupalEntity(?FieldableEntityInterface $entity = NULL): static;

  /**
   * Assign API Sync data to this mapped object, in preparation for saving.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   OData record.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setApiSyncRecord(ODataObjectInterface $oDataRecord): static;

  /**
   * Pull the mapped API Sync object data from remote.
   */
  public function pull(): void;

  /**
   * Based on the Mapped Object revision limit, delete old revisions.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   */
  public function pruneRevisions(EntityStorageInterface $storage): void;

  /**
   * Get the push action.
   *
   * The result is only meaningful during a push.
   *
   * @return \Drupal\apisync_mapping\PushActions
   *   The push actions.
   */
  public function getPushAction(): PushActions;

}
