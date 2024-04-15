<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a set of fields are unique for the given entity type.
 */
class UniqueFieldsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a new UniqueFieldsConstraintValidator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    $entityType = $entity->getEntityType();
    $idKey = $entityType->getKey('id');

    $query = $this->entityTypeManager
      ->getStorage($entityType->id())
      ->getQuery()
      ->accessCheck()
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($idKey, (int) $entity->id(), '<>')
      ->range(0, 1);

    foreach ($constraint->fields as $field) {
      $fieldName = $field;
      if (strpos($fieldName, '.')) {
        [$fieldName, $property] = explode('.', $fieldName, 2);
      }
      else {
        $property = $entity->{$field}->getFieldDefinition()->getMainPropertyName();
      }
      $value = $entity->{$fieldName}->{$property};
      $query->condition($field, $value);
    }

    $id = $query->execute();
    if ($id) {
      $id = reset($id);
      $entity = $this->entityTypeManager
        ->getStorage($entityType->id())
        ->load($id);
      $url = $entity->toUrl();
      $messageReplacements = [
        '@entity_type' => $entityType->getSingularLabel(),
        ':url' => $url->toString(),
        '@label' => $entity->label(),
      ];
      $this->context->addViolation($constraint->message, $messageReplacements);
    }
  }

}
