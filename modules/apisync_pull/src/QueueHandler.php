<?php

declare(strict_types = 1);

namespace Drupal\apisync_pull;

use Drupal\apisync\Event\ApiSyncErrorEvent;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync\OData\SelectQueryResult;
use Drupal\apisync\OData\SelectQueryResultInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\Event\ApiSyncQueryEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\apisync_pull\QueueHandler
 */
class QueueHandler {

  const PULL_MAX_QUEUE_SIZE = 100000;
  const PULL_QUEUE_NAME = 'cron_apisync_pull';

  /**
   * OData client.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $oDataClient;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * All pull mappings.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   */
  protected array $mappings;

  /**
   * API Sync config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructor for a QueueHandler object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $oDataClient
   *   API Sync oData client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Queue\QueueDatabaseFactory $queueFactory
   *   Queue service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $oDataClient,
      EntityTypeManagerInterface $entityTypeManager,
      QueueDatabaseFactory $queueFactory,
      ConfigFactoryInterface $configFactory,
      EventDispatcherInterface $eventDispatcher,
      TimeInterface $time
  ) {
    $this->oDataClient = $oDataClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->queue = $queueFactory->get(self::PULL_QUEUE_NAME);
    $this->config = $configFactory->get('apisync.settings');
    $this->eventDispatcher = $eventDispatcher;

    /** @var \Drupal\apisync_mapping\ApiSyncMappingStorage $mappingStorage */
    $mappingStorage = $this->entityTypeManager->getStorage('apisync_mapping');
    $this->mappings = $mappingStorage->loadCronPullMappings();
  }

  /**
   * Pull updated records from remote and place them in the queue.
   *
   * Executes a query based on defined mappings, loops through the results,
   * and places each updated API Sync object into the queue for later
   * processing.
   *
   * @param bool $forcePull
   *   Whether to force the queried records to be pulled.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use
   *   "now".
   *
   * @return bool
   *   TRUE if there was room to add items, FALSE otherwise.
   */
  public function getUpdatedRecords(bool $forcePull = FALSE, int $start = 0, int $stop = 0): bool {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    $maxSize = $this->config->get('pull_max_queue_size') ?: static::PULL_MAX_QUEUE_SIZE;
    if ($maxSize && $this->queue->numberOfItems() > $maxSize) {
      $message = 'Pull Queue contains %noi items, exceeding the max size of %max items. Pull processing will be blocked until the number of items in the queue is reduced to below the max size.';
      $args = [
        '%noi' => $this->queue->numberOfItems(),
        '%max' => $maxSize,
      ];
      $this->eventDispatcher->dispatch(new ApiSyncNoticeEvent(NULL, $message, $args), ApiSyncEvents::NOTICE);
      return FALSE;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      $this->getUpdatedRecordsForMapping($mapping, $forcePull, $start, $stop);
    }
    return TRUE;
  }

  /**
   * Fetch and enqueue records from remote.
   *
   * Given a mapping and optional timeframe, perform an API query for updated
   * records and enqueue them into the pull queue.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The API Sync mapping for which to query.
   * @param bool $forcePull
   *   Whether to force the queried records to be pulled.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use
   *   "now".
   *
   * @return false|int
   *   Return the number of records fetched by the pull query, or FALSE no
   *   query was executed.
   *
   * @see ApiSyncMappingInterface
   */
  public function getUpdatedRecordsForMapping(
      ApiSyncMappingInterface $mapping,
      bool $forcePull = FALSE,
      int $start = 0,
      int $stop = 0
  ): false|int {
    if (!$mapping->doesPull()) {
      return FALSE;
    }

    if ($start == 0 && $mapping->getNextPullTime() > $this->time->getRequestTime()) {
      // Skip this mapping, based on pull frequency.
      return FALSE;
    }

    $results = $this->doApiSyncObjectQuery($mapping, [], $start, $stop);
    if ($results) {
      $this->enqueueAllResults($mapping, $results, $forcePull);
      return $results->size();
    }
    return FALSE;
  }

  /**
   * Given a single mapping/id pair, enqueue it.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   * @param string $apiSyncId
   *   The record id.
   * @param bool $forcePull
   *   Whether to force a pull. TRUE by default.
   *
   * @return bool
   *   TRUE if the record was enqueued successfully. Otherwise FALSE.
   */
  public function getSingleUpdatedRecord(
      ApiSyncMappingInterface $mapping,
      string $apiSyncId,
      bool $forcePull = TRUE
  ): bool {
    if (!$mapping->doesPull()) {
      return FALSE;
    }
    // We need to load the mapped object in order to get the endpoint path.
    /** @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface $mappedObjectStorage */
    $mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    $mappedObject = $mappedObjectStorage->loadByApiSyncIdAndMapping($apiSyncId, $mapping);
    if (!$mappedObject) {
      return FALSE;
    }
    $record = $this->oDataClient->objectRead($mappedObject->getApiSyncPath());
    if ($record) {
      $results = SelectQueryResult::createSingle($record);
      $this->enqueueAllResults($mapping, $results, $forcePull);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Perform the API Sync Object Query for a mapping and its mapped fields.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping for which to execute pull.
   * @param array $mappedFields
   *   Fetch only these fields, if given, otherwise fetch all mapped fields.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use
   *   "now".
   *
   * @return \Drupal\apisync\OData\SelectQueryResultInterface|null
   *   returned result object from remote.
   *
   * @see ApiSyncMappingInterface
   */
  public function doApiSyncObjectQuery(
      ApiSyncMappingInterface $mapping,
      array $mappedFields = [],
      int $start = 0,
      int $stop = 0
  ): SelectQueryResultInterface|null {
    try {
      $query = $mapping->getPullQuery($mappedFields, $start, $stop);
      $this->eventDispatcher->dispatch(
          new ApiSyncQueryEvent($mapping, $query),
          ApiSyncEvents::PULL_QUERY
      );
      return $this->oDataClient->query($query);
    }
    catch (\Exception $e) {
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(
          new ApiSyncErrorEvent($e, ApiSyncErrorEvent::BASE_ERROR_MESSAGE, $args),
          ApiSyncEvents::ERROR
      );
      return NULL;
    }
  }

  /**
   * Inserts the given records into pull queue.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param \Drupal\apisync\OData\SelectQueryResultInterface $results
   *   Results.
   * @param bool $forcePull
   *   Force flag.
   */
  public function enqueueAllResults(
      ApiSyncMappingInterface $mapping,
      SelectQueryResultInterface $results,
      bool $forcePull = FALSE
  ): void {
    while (!$this->enqueueResultSet($mapping, $results, $forcePull)) {
      try {
        $results = $this->oDataClient->queryMore($results);
      }
      catch (\Exception $e) {
        $args = Error::decodeException($e);
        $this->eventDispatcher->dispatch(
            new ApiSyncErrorEvent($e, ApiSyncErrorEvent::BASE_ERROR_MESSAGE, $args),
            ApiSyncEvents::ERROR
        );
        // @todo do we really want to eat this exception here?
        return;
      }
    }
  }

  /**
   * Enqueue a set of results into pull queue.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping object currently being processed.
   * @param \Drupal\apisync\OData\SelectQueryResultInterface $results
   *   Result record set.
   * @param bool $forcePull
   *   Whether to force pull for enqueued items.
   *
   * @return bool
   *   Returns results->done(): TRUE if there are no more results, or FALSE if
   *   there are additional records to be queried.
   */
  public function enqueueResultSet(
      ApiSyncMappingInterface $mapping,
      SelectQueryResultInterface $results,
      bool $forcePull = FALSE
  ): bool {
    $maxTime = 0;
    $triggerField = $mapping->getPullTriggerDate();
    try {
      foreach ($results->records() as $record) {
        // @todo Add a Pull Queue Enqueue Event.
        $this->enqueueRecord($mapping, $record, $forcePull);
        $recordTime = strtotime($record->field($triggerField));
        if ($maxTime < $recordTime) {
          $maxTime = $recordTime;
          $mapping->setLastPullTime($maxTime);
        }
      }
      return $results->done();
    }
    catch (\Exception $e) {
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(
          new ApiSyncErrorEvent($e, ApiSyncErrorEvent::BASE_ERROR_MESSAGE, $args),
          ApiSyncEvents::ERROR
      );
    }
  }

  /**
   * Enqueue a single record for pull.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   * @param \Drupal\apisync\OData\ODataObjectInterface $record
   *   API Sync data.
   * @param bool $forcePull
   *   If TRUE, ignore timestamps and force data to be pulled.
   *
   * @throws \Exception
   */
  public function enqueueRecord(
      ApiSyncMappingInterface $mapping,
      ODataObjectInterface $record,
      bool $forcePull = FALSE
  ): void {
    $this->queue->createItem(new PullQueueItem($record, $mapping, $forcePull));
  }

}
