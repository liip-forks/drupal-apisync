<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wrapper for the array of values which will be pushed to remote.
 *
 * Usable by apisync.client for push actions: create, update.
 */
class PushParams {

  /**
   * Key-value array of raw data.
   *
   * @var array
   */
  protected array $params;

  /**
   * Mapping for this push params.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * The Drupal entity being parameterized.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $drupalEntity;

  /**
   * Given a Drupal entity, return an array of API Sync key-value pairs.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync Mapping.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity.
   * @param array $params
   *   Initial params values (optional).
   */
  public function __construct(ApiSyncMappingInterface $mapping, EntityInterface $entity, array $params = []) {
    $this->mapping = $mapping;
    $this->drupalEntity = $entity;
    $this->params = $params;
    foreach ($mapping->getFieldMappings() as $fieldPlugin) {
      // Skip fields that aren't being pushed to remote.
      if (!$fieldPlugin->push()) {
        continue;
      }
      $this->params[$fieldPlugin->config('apisync_field')] = $fieldPlugin->pushValue($entity, $mapping);
    }
  }

  /**
   * Mapping getter.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   *   Mapping.
   */
  public function getMapping(): ApiSyncMappingInterface {
    return $this->mapping;
  }

  /**
   * Drupal entity getter.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Drupal entity.
   */
  public function getDrupalEntity(): EntityInterface {
    return $this->drupalEntity;
  }

  /**
   * Get the raw push data.
   *
   * @return array
   *   The push data.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Get a param value for a given key.
   *
   * @param string|int $key
   *   A param key.
   *
   * @return mixed|null
   *   The given param value for $key, or NULL if $key is not set.
   *
   * @see hasParam()
   */
  public function getParam(string|int $key): mixed {
    return $this->hasParam($key) ? $this->params[$key] : NULL;
  }

  /**
   * Return TRUE if the given $key is set.
   *
   * @param string|int $key
   *   A key.
   *
   * @return bool
   *   TRUE if $key is set.
   */
  public function hasParam(string|int $key): bool {
    return array_key_exists($key, $this->params);
  }

  /**
   * Overwrite params wholesale.
   *
   * @param array $params
   *   Array of params to set for thie PushParams.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setParams(array $params): static {
    $this->params = $params;
    return $this;
  }

  /**
   * Set a param.
   *
   * @param string|int $key
   *   Key to set for this param.
   * @param mixed $value
   *   Value to set for this param.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setParam(string|int $key, mixed $value): static {
    $this->params[$key] = $value;
    return $this;
  }

  /**
   * Unset a param value.
   *
   * @param string|int $key
   *   Key to unset for this param.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function unsetParam(string|int $key): static {
    unset($this->params[$key]);
    return $this;
  }

}
