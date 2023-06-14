<?php

declare(strict_types = 1);

namespace Drupal\apisync_push;

use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push controller.
 */
class PushController extends ControllerBase {

  /**
   * Push queue service.
   *
   * @var \Drupal\apisync_push\PushQueueInterface
   */
  protected PushQueueInterface $pushQueue;

  /**
   * Mapping storage.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Constructor for a PushController object.
   *
   * @param \Drupal\apisync_push\PushQueue $pushQueue
   *   Push queue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
      PushQueue $pushQueue,
      EntityTypeManagerInterface $etm,
      ConfigFactoryInterface $configFactory
  ) {
    $this->pushQueue = $pushQueue;
    $this->mappingStorage = $etm->getStorage('apisync_mapping');
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('queue.apisync_push'),
        $container->get('entity_type.manager'),
        $container->get('config.factory')
    );
  }

  /**
   * Page callback to process the entire push queue.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response with code 204.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function endpoint(): Response {
    // "Access Denied" if standalone global config not enabled.
    if (!$this->config('apisync.settings')->get('standalone')) {
      throw new AccessDeniedHttpException();
    }
    $this->pushQueue->processQueues();
    return new Response('', 204);
  }

  /**
   * Page callback to process push queue for a given mapping.
   *
   * @param mixed $apiSyncMapping
   *   The ID of the API Sync mapping.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response with code 204.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function mappingEndpoint(mixed $apiSyncMapping): Response {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
    $mapping = $this->mappingStorage->load($apiSyncMapping);
    // If standalone for this mapping is disabled, and global standalone is
    // disabled, then "Access Denied" for this mapping.
    if (!$mapping->doesPushStandalone()
        && !$this->configFactory->get('apisync.settings')->get('standalone')
    ) {
      throw new AccessDeniedHttpException();
    }
    $this->pushQueue->processQueue($mapping);
    return new Response('', 204);
  }

}
