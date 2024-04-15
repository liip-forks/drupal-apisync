<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedProperties",
 *   label = @Translation("Related Entity Properties")
 * )
 */
class RelatedProperties extends ApiSyncMappingFieldPluginBase {

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
        '#description' => $this->t('Select a property from the referenced field.<br />If more than one entity is referenced, the entity at delta zero will be used.<br />An entity reference field will be used to sync an identifier, e.g. API Sync ID and Node ID.'),
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
   * @return mixed
   *   The outbound value.
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed { // phpcs:ignore
    [$fieldName, $referencedFieldName] = explode(':', $this->config('drupal_field_value'), 2);
    // Since we're not setting hard restrictions around bundles/fields, we may
    // have a field that doesn't exist for the given bundle/entity. In that
    // case, calling get() on an entity with a non-existent field argument
    // causes an exception during entity save. Probably a bug, but I haven't
    // found it in the issue queue. So, just check first to make sure the field
    // exists.
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );
    if (empty($instances[$fieldName])) {
      return NULL;
    }

    $field = $entity->get($fieldName);
    if (empty($field->entity)) {
      // This reference field is blank.
      return NULL;
    }

    return $field->entity->get($referencedFieldName)?->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    $definition['config_dependencies']['config'] = [];
    $fieldName = $this->config('drupal_field_value');
    if (!$fieldName) {
      return $definition;
    }
    if (strpos($fieldName, ':')) {
      [$fieldName] = explode(':', $fieldName, 2);
    }
    // Add reference field.
    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = $this->entityTypeManager->getStorage('field_config')->load(
        $this->mapping->getDrupalEntityType()
        . '.'
        . $this->mapping->getDrupalBundle()
        . '.'
        . $fieldName
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

  /**
   * Form options helper.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync mapping.
   *
   * @return array|null
   *   Form options.
   */
  protected function getConfigurationOptions(ApiSyncMappingInterface $mapping): ?array {
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );
    if (empty($instances)) {
      return NULL;
    }

    $options = [];

    // Loop over every field on the mapped entity. For reference fields, expose
    // all properties of the referenced entity.
    foreach ($instances as $instance) {
      if (!$this->instanceOfEntityReference($instance)) {
        continue;
      }

      $settings = $instance->getSettings();
      $entityTypeId = $settings['target_type'];
      $properties = [];

      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);

      // Exclude non-fieldables.
      if ($entityType->entityClassImplements(FieldableEntityInterface::class)) {
        foreach ($this->entityTypeBundleInfo->getBundleInfo($entityTypeId) as $bundle => $bundle_info) {
          // If target bundles is specified, limit which bundles are visible.
          if (!empty($settings['handler_settings']['target_bundles'])
            && !in_array($bundle, $settings['handler_settings']['target_bundles'])) {
            continue;
          }
          $properties += $this
            ->entityFieldManager
            ->getFieldDefinitions($entityTypeId, $bundle);
        }
      }

      foreach ($properties as $key => $property) {
        $options[(string) $instance->getLabel()][$instance->getName() . ':' . $key] = $property->getLabel();
      }
    }

    if (empty($options)) {
      return NULL;
    }

    // Alphabetize options for UI.
    foreach ($options as &$option_set) {
      asort($option_set);
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldDataDefinition(FieldableEntityInterface $entity): DataDefinitionInterface {
    $fieldName = $this->config('drupal_field_value');

    if (strpos($fieldName, ':')) {
      [$fieldName, $subFieldName] = explode(':', $fieldName, 2);

      /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field */
      $field = $entity->get($fieldName);
      $referencedEntities = $field->referencedEntities();

      if (!empty($referencedEntities)) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
        $entity = reset($referencedEntities);
        $fieldName = $subFieldName;
      }
    }

    return $entity->get($fieldName)
      ->getFieldDefinition()
      ->getItemDefinition();
  }

}
