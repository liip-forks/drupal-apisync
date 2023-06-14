<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "UniqueFields",
 *   label = @Translation("Unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class UniqueFieldsConstraint extends Constraint {

  /**
   * Constraint message.
   *
   * @var string
   */
  public string $message = 'A @entity_type already exists: <a href=":url">@label</a>';

  /**
   * Array of unique fields.
   *
   * @var array
   */
  public array $fields;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): string {
    return 'fields';
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\apisync_mapping\Plugin\Validation\Constraint\UniqueFieldsConstraintValidator';
  }

}
