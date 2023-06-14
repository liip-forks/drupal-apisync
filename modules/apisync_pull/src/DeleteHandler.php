<?php

declare(strict_types = 1);

namespace Drupal\apisync_pull;

use Drupal\apisync\Event\ApiSyncErrorEvent;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncDeleteProviderInterface;
use Drupal\apisync_mapping\ApiSyncIdProviderInterface;
use Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles pull cron deletion of Drupal entities based mapping settings.
 *
 * Note: This is currently project specific and must be generalized.
 * The reason for this is that we are supporting remote soft deletes.
 *
 * @see \Drupal\apisync_pull\DeleteHandler
 */
class DeleteHandler {

  /**
   * OData client object.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $oDataClient;

  /**
   * Entity Manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * API Sync ID provider.
   *
   * @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface
   */
  protected ApiSyncIdProviderInterface $apiSyncIdProvider;

  /**
   * API Sync mapping storage service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Mapped Object storage service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Mapped objects to delete provider.
   *
   * @var \Drupal\apisync_mapping\ApiSyncDeleteProviderInterface
   */
  protected ApiSyncDeleteProviderInterface $apiSyncDeleteProvider;

  /**
   * Constructor for a DeleteHandler object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $oDataClient
   *   OData client object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apiSyncIdProvider
   *   API Sync ID provider.
   * @param \Drupal\apisync_mapping\ApiSyncDeleteProviderInterface $apiSyncDeleteProvider
   *   Mapped objects to delete provider.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $oDataClient,
      EntityTypeManagerInterface $entityTypeManager,
      StateInterface $state,
      EventDispatcherInterface $eventDispatcher,
      ApiSyncIdProviderInterface $apiSyncIdProvider,
      ApiSyncDeleteProviderInterface $apiSyncDeleteProvider
  ) {
    $this->oDataClient = $oDataClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->eventDispatcher = $eventDispatcher;
    $this->apiSyncIdProvider = $apiSyncIdProvider;
    $this->mappingStorage = $this->entityTypeManager->getStorage('apisync_mapping');
    $this->mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    $this->apiSyncDeleteProvider = $apiSyncDeleteProvider;
  }

  /**
   * Process deleted records.
   *
   * @return bool
   *   TRUE.
   */
  public function processDeletedRecords(): bool {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMapping[] $mappings */
    $mappings = $this->mappingStorage->loadMultiple();
    foreach ($mappings as $mapping) {
      if (!$mapping->checkTriggers([MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE])) {
        continue;
      }

      $this->handleMapping($mapping);
    }
    return TRUE;
  }

  /**
   * Handle mapping.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   */
  protected function handleMapping(ApiSyncMappingInterface $mapping): void {
    $mappedObjectIdsToDelete = $this->apiSyncDeleteProvider->getMappedObjectIdsToDelete($mapping);
    foreach ($mappedObjectIdsToDelete as $mappedObjectIdToDelete) {
      /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface|null $mappedObject */
      $mappedObject = $this->mappedObjectStorage->load($mappedObjectIdToDelete);
      $entity = $mappedObject?->getMappedEntity();
      if ($entity) {
        try {
          // This prevents the apisync_pull module from potentially triggering
          // a push for the deletion.
          $entity->apisync_pull = TRUE;
          $entity->delete();
        }
        catch (EntityStorageException $exception) {
          $message = 'Storage exception deleting entity ID %entity_id for mapped object %mapped_object_id with mapping %mapping_id.';
          $args = [
            '%entity_id' => $entity->id(),
            '%mapped_object_id' => $mappedObject->id(),
            '%mapping_id' => $mapping->id(),
          ];
          $this->eventDispatcher->dispatch(
            new ApiSyncErrorEvent($exception, $message, $args));
        }

      }
      try {
        $mappedObject?->delete();
      }
      catch (EntityStorageException $exception) {
        $message = 'Storage exception deleting mapped_object_id %mapped_object_id with mapping %mapping_id';
        $args = [
          '%mapped_object_id' => $mappedObject->id(),
          '%mapping_id' => $mapping->id(),
        ];
        $this->eventDispatcher->dispatch(new ApiSyncErrorEvent($exception, $message, $args));
      }

    }
  }

}
