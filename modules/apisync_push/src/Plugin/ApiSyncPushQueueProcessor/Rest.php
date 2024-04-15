<?php

declare(strict_types=1);

namespace Drupal\apisync_push\Plugin\ApiSyncPushQueueProcessor;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Exception\EntityNotFoundException;
use Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface;
use Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\Event\ApiSyncPushOpEvent;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\apisync_push\PushQueueInterface;
use Drupal\apisync_push\PushQueueProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Rest queue processor plugin.
 *
 * @Plugin(
 *   id = "rest",
 *   label = @Translation("REST Push Queue Processor")
 * )
 */
class Rest extends PluginBase implements PushQueueProcessorInterface {

  /**
   * Push queue service.
   *
   * @var \Drupal\apisync_push\PushQueueInterface
   */
  protected PushQueueInterface $queue;

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
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * ETM service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Auth manager.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderPluginManager
   */
  protected ApiSyncAuthProviderPluginManagerInterface $authManager;

  /**
   * Mapped object factory.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface
   */
  protected ApiSyncMappedObjectFactoryInterface $mappedObjectFactory;

  /**
   * Constructor for a Rest object.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $pluginId
   *   Plugin id.
   * @param array $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\apisync_push\PushQueueInterface $queue
   *   Push queue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   Auth manager.
   * @param \Drupal\apisync_mapping\ApiSyncMappedObjectFactoryInterface $mappedObjectFactory
   *   Mapped object factory.
   */
  public function __construct(
      array $configuration,
      string $pluginId,
      array $pluginDefinition,
      PushQueueInterface $queue,
      EntityTypeManagerInterface $entityTypeManager,
      EventDispatcherInterface $eventDispatcher,
      ApiSyncAuthProviderPluginManagerInterface $authManager,
      ApiSyncMappedObjectFactoryInterface $mappedObjectFactory
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->queue = $queue;
    $this->entityTypeManager = $entityTypeManager;
    $this->mappingStorage = $entityTypeManager->getStorage('apisync_mapping');
    $this->mappedObjectStorage = $entityTypeManager->getStorage('apisync_mapped_object');
    $this->eventDispatcher = $eventDispatcher;
    $this->authManager = $authManager;
    $this->mappedObjectFactory = $mappedObjectFactory;
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
        $configuration,
        $pluginId,
        $pluginDefinition,
        $container->get('queue.apisync_push'),
        $container->get('entity_type.manager'),
        $container->get('event_dispatcher'),
        $container->get('plugin.manager.apisync.auth_providers'),
        $container->get('apisync_mapping.mapped_object_factory')
    );
  }

  /**
   * Process push queue items.
   */
  public function process(array $items): void {
    if (!$this->authManager->getToken()) {
      throw new SuspendQueueException('API Sync client not authorized.');
    }
    foreach ($items as $item) {
      try {
        $this->processItem($item);
        $this->queue->deleteItem($item);
      }
      catch (\Exception $e) {
        $this->queue->failItem($e, $item);
      }
    }
  }

  /**
   * Push queue item process callback.
   *
   * @param object $item
   *   The push queue item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\apisync\Exception\EntityNotFoundException
   */
  public function processItem(object $item): void {
    // Allow exceptions to bubble up for PushQueue to sort things out.
    $mapping = $this->mappingStorage->load($item->name);
    $mappedObject = $this->getMappedObject($item, $mapping);

    if ($mappedObject->isNew()
        && $item->op == MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_DELETE
    ) {
      // If mapped object doesn't exist or fails to load for this delete, this
      // item can be considered successfully processed.
      return;
    }

    try {
      $this->eventDispatcher->dispatch(
          new ApiSyncPushOpEvent($mappedObject, $item->op),
          ApiSyncEvents::PUSH_MAPPING_OBJECT
      );

      // If this is a delete, destroy the API Sync object and we're done.
      if ($item->op == MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_DELETE) {
        $mappedObject->pushDelete();
        // This has to be cleaned up here because we need the object to process
        // Async.
        $mappedObject->delete();
      }
      else {
        $entity = $this->entityTypeManager
          ->getStorage($mapping->drupal_entity_type)
          ->load($item->entity_id);
        if ($entity === NULL) {
          // Bubble this up also.
          throw new EntityNotFoundException($item->entity_id, $mapping->drupal_entity_type);
        }

        // Push to remote. This also saves the mapped object.
        $mappedObject
          ->setDrupalEntity($entity)
          ->push();
      }
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(
          new ApiSyncPushOpEvent($mappedObject, $item->op),
          ApiSyncEvents::PUSH_FAIL
      );

      // Log errors and throw exception to cause this item to be re-queued.
      if (!$mappedObject->isNew()) {
        // Only update existing mapped objects.
        $mappedObject
          ->set('last_sync_action', $item->op)
          ->set('last_sync_status', FALSE)
          ->set('revision_log_message', $e->getMessage())
          ->save();
      }
      throw $e;
    }
  }

  /**
   * Return the mapped object given a queue item and mapping.
   *
   * @param object $item
   *   Push queue item.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface
   *   The mapped object.
   */
  protected function getMappedObject(object $item, ApiSyncMappingInterface $mapping): ApiSyncMappedObjectInterface {
    $mappedObject = FALSE;
    // Prefer mapped object id if we have one.
    if ($item->mapped_object_id) {
      $mappedObject = $this
        ->mappedObjectStorage
        ->load($item->mapped_object_id);
    }
    if ($mappedObject) {
      return $mappedObject;
    }

    // Fall back to entity+mapping, which is a unique key.
    if ($item->entity_id) {
      $mappedObject = $this
        ->mappedObjectStorage
        ->loadByProperties([
          'drupal_entity__target_type' => $mapping->drupal_entity_type,
          'drupal_entity__target_id' => $item->entity_id,
          'apisync_mapping' => $mapping->id(),
        ]);
    }
    if ($mappedObject) {
      if (is_array($mappedObject)) {
        $mappedObject = current($mappedObject);
      }
      return $mappedObject;
    }

    return $this->mappedObjectFactory->create($mapping, NULL, $item->entity_id);
  }

}
