<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;

/**
 * Push op event.
 */
class ApiSyncPushOpEvent extends ApiSyncPushEvent {

  /**
   * The pull operation.
   *
   * One of:
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE.
   *
   * @var string
   */
  protected string $op;

  /**
   * ApiSyncPushOpEvent dispatched when PushParams are not available.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   The mapped object.
   * @param string $op
   *   One of:
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_CREATE
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_UPDATE
   *   \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_DELETE.
   */
  public function __construct(ApiSyncMappedObjectInterface $mappedObject, string $op) {
    parent::__construct($mappedObject);
    $this->op = $op;
  }

  /**
   * Getter for the pull operation.
   *
   * One of:
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
   * \Drupal\apisync_mapping\MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE.
   *
   * @return string
   *   The op.
   */
  public function getOp(): string {
    return $this->op;
  }

}
