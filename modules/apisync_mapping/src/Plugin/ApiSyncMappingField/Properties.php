<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "properties",
 *   label = @Translation("Properties")
 * )
 */
class Properties extends PropertiesBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
      array $form,
      FormStateInterface $formState
  ): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);
    $options = $this->getConfigurationOptions($form['#entity']);

    // Display the plugin config form here:
    if (empty($options)) {
      $pluginForm['drupal_field_value'] = [
        '#markup' => $this->t('No available properties.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a Drupal field or property to map to an API Sync field.<br />Entity Reference fields should be handled using Related Entity Ids or Token field types.'),
      ];
    }

    return $pluginForm;
  }

  /**
   * Form options helper.
   *
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   API Sync mapping.
   *
   * @return array
   *   The form options.
   */
  protected function getConfigurationOptions(ApiSyncMappingInterface $mapping): array {
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );

    $options = [];

    foreach ($fieldDefinitions as $fieldName => $fieldDefinitions) {
      $label = $fieldDefinitions->getLabel();
      if ($this->instanceOfEntityReference($fieldDefinitions)) {
        continue;
      }
      else {
        // Get a list of property definitions.
        $propertyDefinitions = $fieldDefinitions->getFieldStorageDefinition()
          ->getPropertyDefinitions();
        if (count($propertyDefinitions) > 1) {
          foreach ($propertyDefinitions as $property => $propertyDefinition) {
            $options[(string) $label][$fieldName . '.' . $property] = $label . ': ' . $propertyDefinition->getLabel();
          }
        }
        else {
          $options[$fieldName] = $label;
        }
      }
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    $field = $this->entityTypeManager->getStorage('field_config')->load(
        $this->mapping->getDrupalEntityType()
        . '.'
        . $this->mapping->getDrupalBundle()
        . '.'
        . $this->config('drupal_field_value')
    );

    if ($field) {
      $definition['config_dependencies']['config'][] = $field->getConfigDependencyName();
    }
    return $definition;
  }

}
