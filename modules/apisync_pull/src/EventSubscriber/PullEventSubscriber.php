<?php

declare(strict_types=1);

namespace Drupal\apisync_pull\EventSubscriber;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync_mapping\Event\ApiSyncQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Pull Event Subscriber.
 */
class PullEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ApiSyncEvents::PULL_QUERY => 'prePullQuery',
    ];
  }

  /**
   * Prepull query.
   *
   * @param \Drupal\apisync_mapping\Event\ApiSyncQueryEvent $event
   *   Event.
   */
  public function prePullQuery(ApiSyncQueryEvent $event): void {
    $mapping = $event->getMapping();
    $mappedObjectTypeEntity = $mapping->getRelatedApiSyncMappedObjectType();
    if ($mappedObjectTypeEntity === NULL) {
      return;
    }

    $fieldMappings = $mappedObjectTypeEntity->getFieldMappings();
    if (empty($fieldMappings)) {
      return;
    }
    foreach ($fieldMappings as $fieldMapping) {
      // Add mapped fields from the related mapped object type to query.
      $event->getQuery()->addField($fieldMapping['apisync_field']);
    }
  }

}
