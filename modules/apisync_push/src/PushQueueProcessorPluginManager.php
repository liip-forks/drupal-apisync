<?php

declare(strict_types = 1);

namespace Drupal\apisync_push;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for API Sync push queue processors.
 */
class PushQueueProcessorPluginManager extends DefaultPluginManager {

  /**
   * Push queue plugin processor manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   */
  public function __construct(
      \Traversable $namespaces,
      CacheBackendInterface $cacheBackend,
      ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct('Plugin/ApiSyncPushQueueProcessor', $namespaces, $moduleHandler);

    $this->setCacheBackend($cacheBackend, 'apisync_push_queue_processor');
  }

}
