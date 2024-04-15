<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "MappingEntity",
 *   label = @Translation("Mapping-API-Sync-ID unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class MappingEntityConstraint extends UniqueFieldsConstraint {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $options = [
      'fields' => [
        "drupal_entity.target_type",
        "drupal_entity.target_id",
        "apisync_mapping",
      ],
    ];
    parent::__construct($options);
  }

}
