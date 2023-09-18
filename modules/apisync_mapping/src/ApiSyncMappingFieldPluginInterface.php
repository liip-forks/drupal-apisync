<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for API Sync Mapping plugins.
 */
interface ApiSyncMappingFieldPluginInterface extends PluginFormInterface, PluginInspectionInterface, DependentPluginInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Returns label of the mapping field plugin.
   *
   * @return string
   *   The label of the mapping field plugin.
   */
  public function label(): string;

  /**
   * Used for returning values by key.
   *
   * @param string $key
   *   Key of the value.
   *
   * @return array|string|null
   *   Value of the key.
   */
  public function get(string $key): array|string|null;

  /**
   * Used for returning values by key.
   *
   * @param string $key
   *   Key of the value.
   * @param string $value
   *   Value of the key.
   */
  public function set($key, $value);

  /**
   * Given a Drupal entity, return the outbound value.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being mapped.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The parent ApiSyncMapping to which this plugin config belongs.
   *
   * @return mixed
   *   The outbound value.
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed;

  /**
   * Munge the value that's being prepared to push to remote.
   *
   * An extension of ::value, ::pushValue does some basic type-checking and
   * validation against API Sync field types to protect against basic data
   * errors.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being pushed.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return mixed
   *   The value to be pushed to remote.
   *
   * @throws \Drupal\apisync\Exception\Exception
   */
  public function pushValue(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed;

  /**
   * Pull callback for field plugins.
   *
   * This callback is overloaded to serve 2 different use cases.
   * - Use case 1: primitive values
   *   If pullValue() returns a primitive value, callers will attempt to set
   *   the value directly on the parent entity.
   * - Use case 2: typed data
   *   If pullValue() returns a TypedDataInterface, callers will assume the
   *   implementation has set the appropriate value(s). The returned TypedData
   *   will be issued to a ApiSyncEvents::PULL_ENTITY_VALUE event, but will
   *   otherwise be ignored.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $object
   *   The API Sync Object being pulled.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being pulled.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|mixed
   *   If a TypedDataInterface is returned, validate constraints and use
   *   TypedDataManager to set the value on the root entity. Otherwise, set the
   *   value directly via FieldableEntityInterface::set
   */
  public function pullValue(ODataObjectInterface $object, EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed;

  /**
   * Determine whether this plugin is allowed for a given mapping.
   *
   * Given a API Sync Mapping, return TRUE or FALSE whether this field
   * plugin can be added via UI. Not used for validation or any other
   * constraints. This works like a soft dependency.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return bool
   *   TRUE if the field plugin can be added to this mapping.
   *
   * @see \Drupal\apisync_mapping\Plugin\ApiSyncMappingField\Broken
   */
  public static function isAllowed(ApiSyncMappingInterface $mapping): bool;

  /**
   * Get/set a key-value config pair for this plugin.
   *
   * @param string|null $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The config.
   */
  public function config(?string $key = NULL, mixed $value = NULL): mixed;

  /**
   * Whether this plugin supports "push" operations.
   *
   * @return bool
   *   TRUE if this plugin supports push.
   */
  public function push(): bool;

  /**
   * Whether this plugin supports "pull" operations.
   *
   * @return bool
   *   TRUE if this plugin supports pull.
   */
  public function pull(): bool;

  /**
   * On dependency removal, determine if this plugin needs to be removed.
   *
   * @param array $dependencies
   *   Dependencies, as provided to ConfigEntityInterface::onDependencyRemoval.
   *
   * @return bool
   *   TRUE if the field should be removed, otherwise false.
   */
  public function checkFieldMappingDependency(array $dependencies): bool;

}
