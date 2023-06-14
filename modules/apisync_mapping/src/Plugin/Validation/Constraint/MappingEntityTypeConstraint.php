<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "MappingEntityType",
 *   label = @Translation("Mapping-EntityType match constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class MappingEntityTypeConstraint extends Constraint {

  /**
   * Constraint message.
   *
   * @var string
   */
  public string $message = 'Mapping %mapping cannot be used with entity type %entity_type.';

}
