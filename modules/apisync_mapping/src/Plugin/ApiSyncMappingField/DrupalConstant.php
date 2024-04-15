<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "DrupalConstant",
 *   label = @Translation("Drupal Constant")
 * )
 */
class DrupalConstant extends ApiSyncMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
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
        '#description' => $this->t('Select a Drupal field or property to map to a constant.'),
      ];
    }

    // A field to hold the constant value.
    $pluginForm['drupal_constant'] = [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_constant'),
      '#description' => $this->t('Enter a constant value to map to a Drupal field.'),
    ];
    // There is no API Sync field for this mapping.
    unset($pluginForm['apisync_field']);

    // We should only be able to pull a constant value to a Drupal field.
    $pluginForm['direction']['#options'] = [
      MappingConstants::APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL => $pluginForm['direction']['#options'][MappingConstants::APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL],
    ];
    $pluginForm['direction']['#default_value'] =
      MappingConstants::APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL;

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
  private function getConfigurationOptions(ApiSyncMappingInterface $mapping): array {
    $instances = $this->entityFieldManager->getFieldDefinitions(
        $mapping->get('drupal_entity_type'),
        $mapping->get('drupal_bundle')
    );

    $options = [];
    foreach ($instances as $key => $instance) {
      // Entity reference fields are handled elsewhere.
      if ($this->instanceOfEntityReference($instance)) {
        continue;
      }
      $options[$key] = $instance->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed {

  }

  /**
   * {@inheritdoc}
   *
   * @return array|string|null
   *   Drupal constant config
   */
  public function pullValue(
      ODataObjectInterface $object,
      EntityInterface $entity,
      ApiSyncMappingInterface $mapping
  ): array|string|null {
    return $this->config('drupal_constant');
  }

  /**
   * {@inheritdoc}
   */
  public function push(): bool {
    return FALSE;
  }

}
