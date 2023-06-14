<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Event;

use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\apisync_mapping\PushParams;

/**
 * Push params event.
 */
class ApiSyncPushParamsEvent extends ApiSyncPushEvent {

  /**
   * Push params.
   *
   * @var \Drupal\apisync_mapping\PushParams
   */
  protected PushParams $params;

  /**
   * Constructor for a ApiSyncPushParamsEvent object.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject
   *   Mapped object.
   * @param \Drupal\apisync_mapping\PushParams $params
   *   Push params.
   */
  public function __construct(ApiSyncMappedObjectInterface $mappedObject, PushParams $params) {
    parent::__construct($mappedObject);
    $this->params = $params;
    $this->entity = ($params) ? $params->getDrupalEntity() : NULL;
    $this->mapping = ($params) ? $params->getMapping() : NULL;
  }

  /**
   * Push params getter.
   *
   * @return \Drupal\apisync_mapping\PushParams
   *   The push param data to be sent to remote.
   */
  public function getParams() {
    return $this->params;
  }

}
