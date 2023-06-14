<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a API Sync mapping matches entity type of the given entity.
 */
class MappingEntityTypeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    $drupalEntity = $entity->getMappedEntity() ?: $entity->getDrupalEntityStub();
    if (!$drupalEntity) {
      $this->context->addViolation('Validation failed. Please check your input and try again.');
      return;
    }
    if ($drupalEntity->getEntityTypeId() != $entity->getMapping()->getDrupalEntityType()) {
      $this->context->addViolation($constraint->message, [
        '%mapping' => $entity->getMapping()->label(),
        '%entity_type' => $drupalEntity->getEntityType()->getLabel(),
      ]);
    }
  }

}
