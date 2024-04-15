<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "MappingApiSyncId",
 *   label = @Translation("Mapping-api sync unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class MappingApiSyncIdConstraint extends UniqueFieldsConstraint {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $options = ['fields' => ['apisync_id', 'apisync_mapping']];
    parent::__construct($options);
  }

}
