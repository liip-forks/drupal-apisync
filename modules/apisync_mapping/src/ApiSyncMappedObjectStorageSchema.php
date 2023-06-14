<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the mapped object schema handler in order to add some unique keys.
 */
class ApiSyncMappedObjectStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entityType, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entityType, $reset);
    // Backwards compatibility for apisync_mapping_update_8001
    // key is too long if length is 255, so we have to wait until the db update
    // fires to avoid WSOD.
    $schema['apisync_mapped_object']['unique keys'] += [
      'entity__mapping' => [
        'drupal_entity__target_type',
        'apisync_mapping',
        'drupal_entity__target_id',
      ],
    ];

    // MySQL (and likely all relevant DBs) consider unique keys only for
    // non-null values.
    $schema['apisync_mapped_object']['unique keys'] += [
      'apisyncid__mapping' => [
        'apisync_mapping',
        'apisync_id',
      ],
    ];

    $schema['apisync_mapped_object']['fields']['apisync_mapping']['length'] =
    $schema['apisync_mapped_object_revision']['fields']['apisync_mapping']['length'] =
      EntityTypeInterface::ID_MAX_LENGTH;

    return $schema;
  }

}
