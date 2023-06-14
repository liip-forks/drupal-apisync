<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "properties_extended",
 *   label = @Translation("Properties, Extended")
 * )
 */
class PropertiesExtended extends PropertiesBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    $fieldName = $this->config('drupal_field_value');
    if (!$fieldName) {
      return $definition;
    }
    if (strpos($fieldName, '.')) {
      [$fieldName] = explode('.', $fieldName, 2);
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
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);
    $mapping = $form['#entity'];

    // Display the plugin config form here:
    $contextName = 'drupal_field_value';

    // If the form has been submitted already, take the mode from the submitted
    // values, otherwise default to existing configuration. And if that does not
    // exist default to the "input" mode.
    $mode = $formState->get('context_' . $contextName);
    if (!$mode) {
      $mode = 'selector';
      $formState->set('context_' . $contextName, $mode);
    }
    $title = $mode == 'selector' ? $this->t('Data selector') : $this->t('Value');

    $pluginForm[$contextName]['setting'] = [
      '#type' => 'textfield',
      '#title' => $title,
      '#attributes' => ['class' => ['drupal-field-value']],
      '#default_value' => $this->config('drupal_field_value'),
    ];
    $element = &$pluginForm[$contextName]['setting'];
    if ($mode == 'selector') {
      $element['#description'] = $this->t("The data selector helps you drill down into the data available.");
      $element['#autocomplete_route_name'] = 'apisync_mapping.autocomplete_controller_autocomplete';
      $element['#autocomplete_route_parameters'] = [
        'entity_type_id' => $mapping->get('drupal_entity_type'),
        'bundle' => $mapping->get('drupal_bundle'),
      ];
    }
    $value = $mode == 'selector' ? $this->t('Switch to the direct input mode') : $this->t('Switch to data selection');
    $pluginForm[$contextName]['switch_button'] = [
      '#type' => 'submit',
      '#name' => 'context_' . $contextName,
      '#attributes' => ['class' => ['drupal-field-switch-button']],
      '#parameter' => $contextName,
      '#value' => $value,
      '#submit' => [static::class . '::switchContextMode'],
      // Do not validate!
      '#limit_validation_errors' => [],
    ];

    return $pluginForm;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::submitConfigurationForm($form, $formState);

    // Resetting the `drupal_field_value` to just the `setting` portion,
    // which should be a string.
    $configValue = $formState->getValue('config');
    $configValue['drupal_field_value'] = $configValue['drupal_field_value']['setting'];
    $formState->setValue('config', $configValue);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDrupalFieldType(DataDefinitionInterface $dataDefinition): ?string {
    $fieldMainProperty = $dataDefinition;
    if ($dataDefinition instanceof ComplexDataDefinitionInterface) {
      $fieldMainProperty = $dataDefinition
        ->getPropertyDefinition($dataDefinition->getMainPropertyName());
    }

    return $fieldMainProperty ? $fieldMainProperty->getDataType() : NULL;
  }

  /**
   * Submit callback: switch a context to data selector or direct input mode.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as
   *   built by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public static function switchContextMode(array &$form, FormStateInterface $formState): void {
    $elementName = $formState->getTriggeringElement()['#name'];
    $mode = $formState->get($elementName);
    $switchedMode = $mode == 'selector' ? 'input' : 'selector';
    $formState->set($elementName, $switchedMode);
    $formState->setRebuild();
  }

}
