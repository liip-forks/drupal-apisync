<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync\Event\ApiSyncBaseEvent;
use Drupal\apisync\OData\SelectQueryInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Pull query event.
 */
class ApiSyncQueryEvent extends ApiSyncBaseEvent {

  /**
   * The mapping.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * The query.
   *
   * @var \Drupal\apisync\OData\SelectQueryInterface
   */
  protected SelectQueryInterface $query;

  /**
   * Construct a new ApiSyncQueryEvent.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   * @param \Drupal\apisync\OData\SelectQueryInterface $query
   *   The query.
   */
  public function __construct(ApiSyncMappingInterface $mapping, SelectQueryInterface $query) {
    $this->mapping = $mapping;
    $this->query = $query;
  }

  /**
   * Getter.
   *
   * @return \Drupal\apisync\OData\SelectQueryInterface
   *   The query.
   */
  public function getQuery(): SelectQueryInterface {
    return $this->query;
  }

  /**
   * Get mapping.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   *   Mapping.
   */
  public function getMapping(): ApiSyncMappingInterface {
    return $this->mapping;
  }

}
