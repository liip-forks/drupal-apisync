<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Lifted from https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes.
 */
class ODataLinkItemList extends FieldItemList {

  use ComputedItemListTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $entity */
    $entity = $this->getEntity();
    $value = NULL;
    if (!$entity->isNew()) {
      $value = [
        'uri' => $entity->getApiSyncUrl(),
        'title' => $this->t('Remote API URL'),
      ];
      $this->setValue($value);
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
