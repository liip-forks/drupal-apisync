<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync\Exception\Exception as ApiSyncException;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedIDs",
 *   label = @Translation("Related Entity Ids")
 * )
 */
class RelatedIDs extends ApiSyncMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);

    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available entity reference fields.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('If an existing connection is found with the selected entity reference, the linked identifier will be used.<br />For example, API Sync ID for Drupal to Remote, or Node ID for Remote to Drupal.<br />If more than one entity is referenced, the entity at delta zero will be used.'),
      ];
    }
    return $pluginForm;

  }

  /**
   * Given a Drupal entity, return the outbound value.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being mapped.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The parent ApiSyncMapping to which this plugin config belongs.
   *
   * @return string|null
   *   API Sync ID, or NULL if not found.
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): ?string { // phpcs:ignore
    $fieldName = $this->config('drupal_field_value');
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $entity->getEntityTypeId(),
        $entity->bundle()
    );

    if (empty($instances[$fieldName])) {
      return NULL;
    }

    $field = $entity->get($fieldName);
    if (empty($field->getValue()) || is_null($field->entity)) {
      // This reference field is blank or the referenced entity no longer
      // exists.
      return NULL;
    }

    // Now we can actually fetch the referenced entity.
    $fieldSettings = $field->getFieldDefinition()->getSettings();
    $referencedMappings = $this->mappedObjectStorage->loadByDrupal(
        $fieldSettings['target_type'],
        $field->entity->id()
    );
    if (!empty($referencedMappings)) {
      $referencedMapping = reset($referencedMappings);
      return $referencedMapping->apiSyncId();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(
      ODataObjectInterface $object,
      EntityInterface $entity,
      ApiSyncMappingInterface $mapping
  ): mixed {
    if (!$this->pull() || empty($this->config('apisync_field'))) {
      throw new ApiSyncException('No data to pull. API Sync field mapping is not defined.');
    }

    $value = $object->field($this->config('apisync_field'));
    // Empty value means nothing to do here.
    if (empty($value)) {
      return NULL;
    }

    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface[] $mappedObjects */
    $mappedObjects = $this->mappedObjectStorage->loadByProperties([
      'apisync_id' => $value,
      'apisync_mapping' => $mapping->id(),
    ]);
    if (!empty($mappedObjects)) {
      $mappedObject = reset($mappedObjects);
      return $mappedObject->getMappedEntity()
        ? $mappedObject->getMappedEntity()->id()
        : NULL;
    }
  }

  /**
   * Helper to build form options.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync mapping.
   *
   * @return array
   *   Form options.
   */
  private function getConfigurationOptions(ApiSyncMappingInterface $mapping): array {
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );
    $options = [];
    foreach ($instances as $name => $instance) {
      if (!$this->instanceOfEntityReference($instance)) {
        continue;
      }
      // @todo Should we exclude config entities?
      $options[$name] = $instance->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    $definition['config_dependencies']['config'] = [];
    // Add reference field.
    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = $this->entityTypeManager->getStorage('field_config')->load(
        $this->mapping->getDrupalEntityType()
        . '.'
        . $this->mapping->getDrupalBundle()
        . '.'
        . $this->config('drupal_field_value')
    );
    if ($field) {
      $definition['config_dependencies']['config'][] = $field->getConfigDependencyName();
      // Add dependencies of referenced field.
      foreach ($field->getDependencies() as $type => $dependency) {
        foreach ($dependency as $item) {
          $definition['config_dependencies'][$type][] = $item;
        }
      }
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldMappingDependency(array $dependencies): bool {
    $definition = $this->getPluginDefinition();
    foreach ($definition['config_dependencies'] as $type => $dependency) {
      foreach ($dependency as $item) {
        if (!empty($dependencies[$type][$item])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
