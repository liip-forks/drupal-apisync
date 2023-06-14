<?php

declare(strict_types = 1);

namespace Drupal\apisync_pull\Controller;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_pull\DeleteHandler;
use Drupal\apisync_pull\QueueHandler;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push Controller.
 */
class PullController extends ControllerBase {

  const DEFAULT_TIME_LIMIT = 30;

  /**
   * Pull queue handler service.
   *
   * @var \Drupal\apisync_pull\QueueHandler
   */
  protected QueueHandler $queueHandler;

  /**
   * Pull delete handler service.
   *
   * @var \Drupal\apisync_pull\DeleteHandler
   */
  protected DeleteHandler $deleteHandler;

  /**
   * Mapping storage.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueService;

  /**
   * Queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueWorkerManager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected Time $time;

  /**
   * Current Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructor for a PullController object.
   *
   * @param \Drupal\apisync_pull\QueueHandler $queueHandler
   *   Pull queue handler service.
   * @param \Drupal\apisync_pull\DeleteHandler $deleteHandler
   *   Pull delete handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\State\StateInterface $stateService
   *   State service.
   * @param \Drupal\Core\Queue\QueueFactory $queueService
   *   Queue factory service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   *   Queue worker manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\Component\Datetime\Time $time
   *   Time.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      QueueHandler $queueHandler,
      DeleteHandler $deleteHandler,
      EntityTypeManagerInterface $entityTypeManager,
      ConfigFactoryInterface $configFactory,
      StateInterface $stateService,
      QueueFactory $queueService,
      QueueWorkerManagerInterface $queueWorkerManager,
      EventDispatcherInterface $eventDispatcher,
      Time $time,
      RequestStack $requestStack
  ) {
    $this->queueHandler = $queueHandler;
    $this->deleteHandler = $deleteHandler;
    $this->mappingStorage = $entityTypeManager->getStorage('apisync_mapping');
    $this->configFactory = $configFactory;
    $this->stateService = $stateService;
    $this->queueService = $queueService;
    $this->queueWorkerManager = $queueWorkerManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('apisync_pull.queue_handler'),
        $container->get('apisync_pull.delete_handler'),
        $container->get('entity_type.manager'),
        $container->get('config.factory'),
        $container->get('state'),
        $container->get('queue'),
        $container->get('plugin.manager.queue_worker'),
        $container->get('event_dispatcher'),
        $container->get('datetime.time'),
        $container->get('request_stack')
    );
  }

  /**
   * Page callback to process push queue for a given mapping.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface|null $apiSyncMapping
   *   API Sync mapping.
   * @param string|null $key
   *   Cron key.
   * @param string|null $id
   *   API Sync ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function endpoint(
      ?ApiSyncMappingInterface $apiSyncMapping = NULL,
      string $key = NULL,
      ?string $id = NULL
  ): Response {
    // If standalone for this mapping is disabled, and global standalone is
    // disabled, then "Access Denied" for this mapping.
    if ($key != $this->stateService->get('system.cron_key')) {
      throw new AccessDeniedHttpException();
    }
    $globalStandalone = $this->config('apisync.settings')->get('standalone');
    if (!$apiSyncMapping && !$globalStandalone) {
      throw new AccessDeniedHttpException();
    }
    if ($apiSyncMapping && !$apiSyncMapping->doesPullStandalone() && !$globalStandalone) {
      throw new AccessDeniedHttpException();
    }
    $this->populateQueue($apiSyncMapping, $id);
    $this->processQueue();
    if ($this->request->get('destination')) {
      return new RedirectResponse($this->request->get('destination'));
    }
    return new Response('', 204);
  }

  /**
   * Helper method to populate queue, optionally by mapping or a single record.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface|null $mapping
   *   API Sync mapping.
   * @param string|null $apiSyncId
   *   API Sync ID.
   */
  protected function populateQueue(?ApiSyncMappingInterface $mapping = NULL, ?string $apiSyncId = NULL): void {
    $mappings = [];
    if ($apiSyncId) {
      $this->queueHandler->getSingleUpdatedRecord($mapping, $apiSyncId, TRUE);
      return;
    }

    if ($mapping != NULL) {
      $mappings[] = $mapping;
    }
    else {
      $mappings = $this->mappingStorage->loadByProperties(["pull_standalone" => TRUE]);
    }

    foreach ($mappings as $currentMapping) {
      $this->queueHandler->getUpdatedRecordsForMapping($currentMapping);
    }
  }

  /**
   * Helper method to get queue processing time limit.
   *
   * @return int
   *   The time limit.
   */
  protected function getTimeLimit(): int {
    return self::DEFAULT_TIME_LIMIT;
  }

  /**
   * Helper method to process queue.
   */
  protected function processQueue(): void {
    $start = microtime(TRUE);
    $worker = $this->queueWorkerManager->createInstance(QueueHandler::PULL_QUEUE_NAME);
    $end = time() + $this->getTimeLimit();
    $queue = $this->queueService->get(QueueHandler::PULL_QUEUE_NAME);
    $count = 0;
    $item = $queue->claimItem();
    while ((!$this->getTimeLimit() || time() < $end) && $item) {
      try {
        $this->eventDispatcher->dispatch(
            new ApiSyncNoticeEvent(
                NULL,
                'Processing item @id from @name queue.',
                [
                  '@name' => QueueHandler::PULL_QUEUE_NAME,
                  '@id' => $item->item_id,
                ]
            ),
            ApiSyncEvents::NOTICE
        );
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
        $item = $queue->claimItem();
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item.
        $queue->releaseItem($item);
        throw new \Exception($e->getMessage());
      }
    }
    // Release item if process times out.
    if ($item) {
      $queue->releaseItem($item);
    }
    $elapsed = microtime(TRUE) - $start;
    $this->eventDispatcher->dispatch(
        new ApiSyncNoticeEvent(
            NULL,
            'Processed @count items from the @name queue in @elapsed sec.',
            [
              '@count' => $count,
              '@name' => QueueHandler::PULL_QUEUE_NAME,
              '@elapsed' => round($elapsed, 2),
            ]
        ),
        ApiSyncEvents::NOTICE
    );
  }

}
