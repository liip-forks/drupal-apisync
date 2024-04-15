<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping_ui\Plugin\Menu\LocalAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Local action for API sync mapped objects.
 */
class ApiSyncMappedObjectAddLocalAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL): string {
    return (string) $this->t('Create Mapped Object');
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $routeMatch): array {
    // If our local action is appearing contextually on an entity, provide
    // contextual entity paramaters to the add form link.
    $options = parent::getOptions($routeMatch);
    $entityTypeId = $routeMatch->getRouteObject()->getOption('_apisync_entity_type_id');
    if (empty($entityTypeId)) {
      return $options;
    }
    $entity = $routeMatch->getParameter($entityTypeId);
    if (!$entity || !($entity instanceof EntityInterface)) {
      return $options;
    }
    $options['query'] = [
      'entity_type_id' => $entityTypeId,
      'entity_id' => $entity->id(),
    ];
    return $options;
  }

}
