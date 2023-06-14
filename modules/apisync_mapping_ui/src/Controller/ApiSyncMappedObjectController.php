<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for ApiSyncMappedObjectController routes.
 */
class ApiSyncMappedObjectController extends ControllerBase {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructor for a ApiSyncMappedObjectController object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager) {
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('current_route_match'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * Access callback for Mapped Object entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   AccessResult::forbidden() or AccessResult::allowed().
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (!$account->hasPermission('administer apisync')) {
      return AccessResult::forbidden();
    }

    // There must be a better way to get the entity from a route match.
    $param = current($this->routeMatch->getParameters()->all());
    if (!is_object($param)) {
      return AccessResult::forbidden();
    }
    $implements = class_implements($param);
    if (empty($implements['Drupal\Core\Entity\EntityInterface'])) {
      return AccessResult::forbidden();
    }
    // Only allow access for entities with mappings.
    /** @var \Drupal\apisync_mapping\ApiSyncMappingStorage $mappingStorage */
    $mappingStorage = $this->entityTypeManager->getStorage('apisync_mapping');
    return $mappingStorage->loadByEntity($param)
      ? AccessResult::allowed()
      : AccessResult::forbidden();
  }

  /**
   * Helper function to get entity from router path.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Drupal entity mapped by the given mapped object.
   *
   * @throws \Exception
   *   If an EntityInterface is not found at the given route.
   */
  private function getEntity(RouteMatchInterface $routeMatch): EntityInterface {
    $parameterName = $routeMatch->getRouteObject()->getOption('_apisync_entity_type_id');
    if (empty($parameterName)) {
      throw new \Exception('Entity type paramater not found.');
    }

    $entity = $routeMatch->getParameter($parameterName);
    if (!$entity || !($entity instanceof EntityInterface)) {
      throw new \Exception('Entity is not of type EntityInterface');
    }

    return $entity;
  }

  /**
   * Helper function to fetch existing ApiSyncMappedObject or create a new one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be mapped.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface[]
   *   The Mapped Objects corresponding to the given entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getMappedObjects(EntityInterface $entity): array {
    /** @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface $mappedObjectStorage */
    $mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    return $mappedObjectStorage->loadByEntity($entity);
  }

  /**
   * List mapped objects for the entity along the current route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   A RouteMatch object.
   *
   * @return array
   *   Array of page elements to render.
   *
   * @throws \Exception
   */
  public function listing(RouteMatchInterface $routeMatch): array {
    $entity = $this->getEntity($routeMatch);
    $apisyncMappedObjects = $this->getMappedObjects($entity);
    if (empty($apisyncMappedObjects)) {
      return [
        '#markup' => $this->t('No mapped objects for %label.', ['%label' => $entity->label()]),
      ];
    }

    // Show the entity view for the mapped object.
    /** @var \Drupal\apisync_mapping_ui\ApiSyncMappedObjectList */
    $builder = $this->entityTypeManager->getListBuilder('apisync_mapped_object');
    return $builder->setEntityIds(array_keys($apisyncMappedObjects))->render();
  }

}
