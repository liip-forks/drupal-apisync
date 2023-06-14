<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * For now, just some dynamic route names.
 */
class ApiSyncMappingController extends EntityController {

  /**
   * Provides a callback for a mapping edit page Title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The title for the mapping edit page, if an entity was found.
   */
  public function editTitle(RouteMatchInterface $routeMatch, ?EntityInterface $entity = NULL): ?TranslatableMarkup {
    $entity = $this->doGetEntity($routeMatch, $entity);
    if ($entity !== NULL) {
      return $this->t("%label Mapping Settings", [
        '%label' => $entity->label(),
      ]);
    }
    return $this->t('New Mapping');
  }

  /**
   * Provides a callback for a mapping field config page Title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The title for the mapping edit page, if an entity was found.
   */
  public function fieldsTitle(RouteMatchInterface $routeMatch, ?EntityInterface $entity = NULL): ?TranslatableMarkup {
    $entity = $this->doGetEntity($routeMatch, $entity);
    if ($entity !== NULL) {
      return $this->t("%label Mapping Fields", [
        '%label' => $entity->label(),
      ]);
    }
    return NULL;
  }

}
