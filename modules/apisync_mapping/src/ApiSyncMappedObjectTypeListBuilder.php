<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of api sync mapped object types.
 */
class ApiSyncMappedObjectTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'apisync_mapped_object_type_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['field_mappings'] = $this->t('Field mappings');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface $entity
   *   The entity for this row of the list.
   */
  public function buildRow(EntityInterface $entity): array { // phpcs:ignore
    assert($entity instanceof ApiSyncMappedObjectTypeInterface);
    if (!empty($entity->getFieldMappings())) {
      $encoded = json_encode($entity->getFieldMappings(), JSON_PRETTY_PRINT) ?: '';
    }
    else {
      $encoded = json_encode([], JSON_PRETTY_PRINT) ?: '';
    }
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['field_mappings'] = new FormattableMarkup('<pre>' . $encoded . '</pre>', []);
    return $row + parent::buildRow($entity);
  }

}
