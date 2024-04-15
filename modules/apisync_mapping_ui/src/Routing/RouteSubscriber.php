<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping_ui\Routing;

use Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;
  /**
   * The mappable entity types service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface
   */
  protected ApiSyncMappableEntityTypesInterface $mappable;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityManager) {
    $this->entityTypeManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      // If the entity didn't get a apisync link template added by
      // hook_entity_types_alter(), skip it.
      $path = $entityType->getLinkTemplate('apisync');
      if (!$path) {
        continue;
      }

      // Create the "listing" route to show all the mapped objects for this
      // entity.
      $route = new Route($path);
      $route
        ->addDefaults([
          '_controller' => "\Drupal\apisync_mapping_ui\Controller\ApiSyncMappedObjectController::listing",
          '_title' => "API Sync mapped objects",
        ])
        ->addRequirements([
          '_custom_access' => '\Drupal\apisync_mapping_ui\Controller\ApiSyncMappedObjectController::access',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_apisync_entity_type_id', $entityTypeId)
        ->setOption('parameters', [
          $entityTypeId => ['type' => 'entity:' . $entityTypeId],
        ]);
      $collection->add("entity.$entityTypeId.apisync", $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
