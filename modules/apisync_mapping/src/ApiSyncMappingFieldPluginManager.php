<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for all views plugins.
 */
class ApiSyncMappingFieldPluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs a ApiSyncMappingFieldPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
      \Traversable $namespaces,
      CacheBackendInterface $cacheBackend,
      ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct('Plugin/ApiSyncMappingField', $namespaces, $moduleHandler);

    $this->setCacheBackend($cacheBackend, 'apisync_mapping_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($pluginId, array $configuration = []): string {
    return 'broken';
  }

}
