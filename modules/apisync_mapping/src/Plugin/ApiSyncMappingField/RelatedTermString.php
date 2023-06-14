<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync\Exception\Exception as ApiSyncException;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedTermString",
 *   label = @Translation("Related Term String")
 * )
 */
class RelatedTermString extends ApiSyncMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);

    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available taxonomy reference fields.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a taxonomy reference field.<br />If more than one term is referenced, the term at delta zero will be used.<br />A taxonomy reference field will be used to sync to the term name.<br />If a term with the given string does not exist one will be created.'),
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

    // Map the term name to the API Sync field.
    return $field->entity->getName();
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

    assert($entity instanceof FieldableEntityInterface);
    $field = $entity->get($this->config('drupal_field_value'));
    if (empty($field)) {
      return NULL;
    }

    $value = $object->field($this->config('apisync_field'));
    // Empty value means nothing to do here.
    if (empty($value)) {
      return NULL;
    }

    // Get the appropriate vocab from the field settings.
    $vocabs = $field->getSetting('handler_settings')['target_bundles'];

    // Look for a term that matches the string in the API Sync field.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $query->condition('vid', $vocabs, 'IN');
    $query->condition('name', $value);
    $termIds = $query->execute();

    if (!empty($termIds)) {
      return reset($termIds);
    }

    // If we cant find an existing term, create a new one.
    $vocab = reset($vocabs);

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'name' => $value,
      'vid' => $vocab,
    ]);
    $term->save();
    return $term->id();
  }

  /**
   * Helper to build form options.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Form mapping entity.
   *
   * @return array
   *   The form options.
   */
  private function getConfigurationOptions(ApiSyncMappingInterface $mapping): array {
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );
    $options = [];
    foreach ($instances as $name => $instance) {
      $hand = $instance->getSetting('handler');
      if ($hand != "default:taxonomy_term") {
        continue;
      }
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
