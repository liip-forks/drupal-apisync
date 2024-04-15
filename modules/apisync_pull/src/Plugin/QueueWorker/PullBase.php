<?php

declare(strict_types=1);

namespace Drupal\apisync_pull\Plugin\QueueWorker;

use Drupal\apisync\Event\ApiSyncErrorEvent;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\ApiSyncIdProviderInterface;
use Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface;
use Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\Event\ApiSyncPullEvent;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides base functionality for the API Sync Pull Queue Workers.
 */
abstract class PullBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * OData client.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $client;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * API Sync ID Provider.
   *
   * @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface
   */
  protected ApiSyncIdProviderInterface $apiSyncIdProvider;

  /**
   * Mapped object factor.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface
   */
  protected ApiSyncMappedObjectFactoryInterface $mappedObjectFactory;

  /**
   * Storage handler for API Sync mappings.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Storage handler for Mapped Objects.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Creates a new PullBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   OData client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apiSyncIdProvider
   *   API Sync ID Provider.
   * @param \Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface $mappedObjectFactory
   *   Mapped object factor.
   * @param array $configuration
   *   The configuration.
   * @param string $pluginId
   *   The plugin ID.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   */
  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      ODataClientInterface $client,
      EventDispatcherInterface $eventDispatcher,
      ApiSyncIdProviderInterface $apiSyncIdProvider,
      ApiSyncMappedObjectFactoryInterface $mappedObjectFactory,
      array $configuration,
      string $pluginId,
      $pluginDefinition
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->client = $client;
    $this->eventDispatcher = $eventDispatcher;
    $this->apiSyncIdProvider = $apiSyncIdProvider;
    $this->mappedObjectFactory = $mappedObjectFactory;
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      array $configuration,
      $pluginId,
      $pluginDefinition
  ): static {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('apisync.odata_client'),
        $container->get('event_dispatcher'),
        $container->get('apisync_mapping.apisync_id_provider'),
        $container->get('apisync_mapping.mapped_object_factory'),
        $configuration,
        $pluginId,
        $pluginDefinition
    );
  }

  /**
   * Queue item process callback.
   *
   * @param \Drupal\apisync_pull\PullQueueItem $item
   *   Pull queue item. Note: typehint missing because we can't change the
   *   inherited API.
   *
   * @return string|null
   *   Return \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   *   or Return \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   *   on successful update or create, NULL otherwise.
   *
   * @throws \Exception
   */
  public function processItem($item): string|null { // phpcs:ignore
    $oDataRecord = $item->getObject();
    $mapping = $this->getMappingStorage()->load($item->getMappingId());
    if (!$mapping) {
      return NULL;
    }

    // loadMappedObjects returns an array, but providing apisync_id and
    // mapping guarantees at most one result.
    $apiSyncId = $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping);
    $mappedObject = $this->getMappedObjectStorage()->loadByProperties([
      'apisync_id' => $apiSyncId,
      'apisync_mapping' => $mapping->id(),
    ]);

    $mappedObject = current($mappedObject);
    if (!empty($mappedObject)) {
      return $this->updateEntity($mapping, $mappedObject, $oDataRecord, $item->getForcePull());
    }

    return $this->createEntity($mapping, $oDataRecord);
  }

  /**
   * Update an existing Drupal entity.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Object of field maps.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   API Sync Mapped object.
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   Current API Sync record array.
   * @param bool $forcePull
   *   If true, ignore entity and API Sync timestamps.
   *
   * @return string|null
   *   Return \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   *   on successful update, NULL otherwise.
   *
   * @throws \Exception
   */
  protected function updateEntity(
      ApiSyncMappingInterface $mapping,
      ApiSyncMappedObjectInterface $mappedObject,
      ODataObjectInterface $oDataRecord,
      bool $forcePull = FALSE
  ): ?string {
    if (!$mapping->checkTriggers([MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE])) {
      return NULL;
    }

    try {
      $entity = $mappedObject->getMappedEntity();
      if (!$entity) {
        $this->eventDispatcher->dispatch(
            new ApiSyncErrorEvent(
                NULL,
                'Drupal entity existed at one time for API Sync object %apiSyncId, but does not currently exist.',
                [
                  '%apiSyncId' => $this->apiSyncIdProvider
                    ->getApiSyncId($oDataRecord, $mapping),
                ]
            ),
            ApiSyncEvents::ERROR
        );
        return NULL;
      }

      // Flag this entity as being synchronized. This does not persist,
      // but is used by apisync_push to avoid duplicate processing.
      if ($entity instanceof SynchronizableInterface) {
        $entity->setSyncing(TRUE);
      }

      $entityUpdated = !empty($entity->changed->value)
        ? $entity->changed->value
        : $mappedObject->getChanged();
      $pullTriggerDate = $mapping->getPullTriggerDate() ? $oDataRecord->field($mapping->getPullTriggerDate()) : NULL;

      // We set this to null if we don't know when it was last updated.
      $apiSyncRecordUpdated = is_string($pullTriggerDate) ? strtotime($pullTriggerDate) : NULL;

      $mappedObject
        ->setDrupalEntity($entity)
        ->setApiSyncRecord($oDataRecord);

      $event = $this->eventDispatcher->dispatch(
          new ApiSyncPullEvent($mappedObject, MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE),
          ApiSyncEvents::PULL_PREPULL
      );
      if (!$event->isPullAllowed()) {
        $this->eventDispatcher->dispatch(
            new ApiSyncNoticeEvent(
                NULL, 'Pull was not allowed for %label with %apiSyncId',
                [
                  '%label' => $entity->label(),
                  '%apiSyncId' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
                ]
            ),
            ApiSyncEvents::NOTICE
        );
        return NULL;
      }
      // @todo Store the eTag and use this to determine if the entity has been
      // updated if we don't have support for the last modified (updated) date.
      if (
        $forcePull ||
        $apiSyncRecordUpdated === NULL ||
        $apiSyncRecordUpdated > $entityUpdated ||
        $mappedObject->get('force_pull')->value
      ) {
        // Set fields values on the Drupal entity.
        $mappedObject->pull();
        $this->eventDispatcher->dispatch(
            new ApiSyncNoticeEvent(
                NULL,
                'Updated entity %label associated with API Sync Object ID: %apiSyncId',
                [
                  '%label' => $entity->label(),
                  '%apiSyncId' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
                ]
              ),
            ApiSyncEvents::NOTICE
        );
        return MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE;
      }

      return NULL;
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(
          new ApiSyncErrorEvent(
              $e,
              'Failed to update entity %label from API Sync object %apiSyncId.',
              [
                '%label' => (isset($entity)) ? $entity->label() : "Unknown",
                '%apiSyncId' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
              ]
          ),
          ApiSyncEvents::WARNING
      );
      // Throwing a new exception keeps current item in cron queue.
      throw $e;
    }
  }

  /**
   * Create a Drupal entity and mapped object.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Object of field maps.
   * @param \Drupal\apisync\OData\ODataObjectInterface $oDataRecord
   *   Current API Sync record array.
   *
   * @return string|null
   *   Return \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   *   on successful create, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createEntity(ApiSyncMappingInterface $mapping, ODataObjectInterface $oDataRecord): string|null {
    if (!$mapping->checkTriggers([MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE])) {
      return NULL;
    }

    try {
      // Define values to pass to entity_create().
      $entityType = $mapping->getDrupalEntityType();
      $entityKeys = $this->entityTypeManager->getDefinition($entityType)->getKeys();
      $values = [];
      if (isset($entityKeys['bundle'])
          && !empty($entityKeys['bundle'])
      ) {
        $values[$entityKeys['bundle']] = $mapping->getDrupalBundle();
      }

      // See note above about flag.
      $values['apisync_pull'] = TRUE;

      // Create entity.
      $entity = $this->entityTypeManager
        ->getStorage($entityType)
        ->create($values);

      $mappedObject = $this->mappedObjectFactory->createFromRecord($mapping, $oDataRecord);

      $mappedObject
        ->setDrupalEntity($entity)
        ->setApiSyncRecord($oDataRecord);

      $event = $this->eventDispatcher->dispatch(
          new ApiSyncPullEvent($mappedObject, MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE),
          ApiSyncEvents::PULL_PREPULL
      );
      if (!$event->isPullAllowed()) {
        $this->eventDispatcher->dispatch(
            new ApiSyncNoticeEvent(
                NULL,
                'Pull was not allowed for %label with %apiSyncId',
                [
                  '%label' => $entity->label(),
                  '%apiSyncId' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
                ]
            ),
            ApiSyncEvents::NOTICE
        );
        return NULL;
      }

      $mappedObject->pull();

      $this->eventDispatcher->dispatch(
          new ApiSyncNoticeEvent(
              NULL,
              'Created entity %id %label associated with API Sync Object ID: %apiSyncId',
              [
                '%id' => $entity->id(),
                '%label' => $entity->label(),
                '%apiSyncId' => $this->apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping),
              ]
          ),
          ApiSyncEvents::NOTICE
      );

      return MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE;
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(
          new ApiSyncNoticeEvent(
              $e,
              'Pull-create failed for API Sync Object ID: %apiSyncId',
              [
                '%apiSyncId' => $this->apiSyncIdProvider
                  ->getApiSyncId($oDataRecord, $mapping),
              ]
          ),
          ApiSyncEvents::WARNING
      );
      // Throwing a new exception to keep current item in cron queue.
      throw $e;
    }
  }

  /**
   * Get mapping storage.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingStorage
   *   Mapping storage.
   */
  protected function getMappingStorage():  ApiSyncMappingStorage {
    if (!isset($this->mappingStorage)) {
      $this->mappingStorage = $this->entityTypeManager->getStorage('apisync_mapping');
    }
    return $this->mappingStorage;
  }

  /**
   * Get mapped object storage.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   *   Mapped object storage.
   */
  protected function getMappedObjectStorage(): ApiSyncMappedObjectStorageInterface {
    if (!isset($this->mappedObjectStorage)) {
      $this->mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    }
    return $this->mappedObjectStorage;
  }

}
