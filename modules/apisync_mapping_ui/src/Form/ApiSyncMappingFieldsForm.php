<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface as FieldPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;

/**
 * API Sync Mapping Fields Form.
 */
class ApiSyncMappingFieldsForm extends ApiSyncMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->ensureConnection('objectDescribe', [
      $this->entity->getApiSyncObjectType(),
      TRUE,
    ])) {
      return $form;
    }
    $form = parent::buildForm($form, $form_state);

    // Add #entity property to expose it to our field plugin forms.
    $form['#entity'] = $this->entity;

    $form['#attached']['library'][] = 'apisync/admin';
    // This needs to be loaded now as it can't be loaded via AJAX for the AC
    // enabled fields.
    $form['#attached']['library'][] = 'core/drupal.autocomplete';

    // For each field on the map, add a row to our table.
    $form['overview'] = ['#markup' => 'Field mapping overview goes here.'];

    $form['field_mappings_wrapper'] = [
      '#title' => $this->t('Mapped Fields'),
      '#type' => 'fieldset',
    ];

    $fieldMappingsWrapper = &$form['field_mappings_wrapper'];
    // Check to see if we have enough information to allow mapping fields.  If
    // not, tell the user what is needed in order to have the field map show up.
    $fieldMappingsWrapper['field_mappings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#prefix' => '<div id="edit-field-mappings">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['container-striped']],
    ];
    $rows = &$fieldMappingsWrapper['field_mappings'];

    $form['field_mappings_wrapper']['ajax_warning'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-ajax-warning',
      ],
    ];

    $addFieldText = !empty($this->entity->getFieldMappings())
      ? $this->t('Add another field mapping')
      : $this->t('Add a field mapping to get started');

    $form['buttons'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['buttons']['field_type'] = [
      '#title' => $this->t('Field Type'),
      '#type' => 'select',
      '#options' => $this->getDrupalTypeOptions($this->entity),
      '#attributes' => ['id' => 'edit-mapping-add-field-type'],
      '#empty_option' => $this->t('- Select -'),
    ];
    $form['buttons']['add'] = [
      '#value' => $addFieldText,
      '#type' => 'submit',
      '#limit_validation_errors' => [['buttons']],
      '#submit' => ['::addField'],
      '#ajax' => [
        'callback' => [$this, 'fieldAddCallback'],
        'wrapper' => 'edit-field-mappings',
      ],
      '#states' => [
        'disabled' => [
          ':input#edit-mapping-add-field-type' => ['value' => ''],
        ],
      ],
    ];

    // Add a row for each saved mapping.
    foreach ($this->entity->getFieldMappings() as $fieldPlugin) {
      $rows[] = $this->getRow($form, $form_state, $fieldPlugin);
    }

    // Add a new row in case it was just added.
    $values = &$form_state->getValues();
    $newField = NestedArray::getValue($values, ['buttons', 'new_field']);
    if (!empty($newField)) {
      $rows[] = $this->getRow($form, $form_state);
      NestedArray::unsetValue($values, ['buttons', 'new_field']);
    }

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  /**
   * Helper function to return an empty row for the field mapping form.
   *
   * @param array $form
   *   The current state of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface|null $fieldPlugin
   *   The field plugin.
   *
   * @return array
   *   The row.
   */
  private function getRow(
      array $form,
      FormStateInterface $formState,
      ?FieldPluginInterface $fieldPlugin = NULL
  ): array {
    $values = &$formState->getValues();
    if ($fieldPlugin == NULL) {
      $fieldType = NestedArray::getValue($values, ['buttons', 'new_field']);
      $fieldPluginDefinition = $this->getFieldPlugin($fieldType);
      $configuration = [
        'mapping' => $this->entity,
        'id' => count(Element::children($form['field_mappings_wrapper']['field_mappings'])),
        'drupal_field_type' => $fieldType,
      ];
      /** @var \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface $fieldPlugin */
      $fieldPlugin = $this->mappingFieldPluginManager->createInstance(
          $fieldPluginDefinition['id'],
          $configuration
      );
      $fieldMappingPlugins = $this->entity->getFieldMappings();
      $config = [];
      foreach ($fieldMappingPlugins as $plugin) {
        $config[] = $plugin->getConfiguration();
      }
      $config[] = $fieldPlugin->getConfiguration();
      $this->entity->set('field_mappings', $config);
    }

    $row['config'] = $fieldPlugin->buildConfigurationForm($form, $formState);
    $row['config']['id'] =
      [
        '#type' => 'value',
        '#value' => $fieldPlugin->config('id'),
      ];

    $operations = [
      'delete' => $this->t('Delete'),
    ];
    $defaults = [];
    $row['ops'] = [
      '#title' => $this->t('Operations'),
      '#type' => 'checkboxes',
      '#options' => $operations,
      '#default_value' => $defaults,
      '#attributes' => ['class' => ['narrow']],
    ];
    $row['drupal_field_type'] = [
      '#type' => 'hidden',
      '#value' => $fieldPlugin->getPluginId(),
    ];
    $row['#type'] = 'container';
    $row['#attributes'] = [
      'class' => [
        'field_mapping_field',
        'row',
        $fieldPlugin->config('id') % 2 ? 'odd' : 'even',
      ],
    ];
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    parent::validateForm($form, $formState);

    // Transform data from the operations column into the expected schema.
    // Copy the submitted values so we don't run into problems with array
    // indexing while removing delete field mappings.
    $values = $formState->getValues();
    if (empty($values['field_mappings'])) {
      // No mappings have been added, no validation to be done.
      return;
    }

    foreach ($values['field_mappings'] as $i => $value) {
      // If a field was deleted, delete it!
      if (!empty($value['ops']['delete'])) {
        $formState->unsetValue(["field_mappings", "$i"]);
        continue;
      }

      // Pass validation to field plugins before performing mapping validation.
      $fieldPlugin = $this->entity->getFieldMapping($value);
      $subFormState = SubformState::createForSubform(
          $form['field_mappings_wrapper']['field_mappings'][$i],
          $form,
          $formState
      );
      $fieldPlugin->validateConfigurationForm($form['field_mappings_wrapper']['field_mappings'][$i], $subFormState);

    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    // Need to transform the schema slightly to remove the "config" dereference.
    // Also trigger submit handlers on plugins.
    $formState->unsetValue(['buttons', 'field_type', 'ops']);

    $values = &$formState->getValues();
    foreach ($values['field_mappings'] as $i => &$value) {
      // Pass submit values to plugin submit handler.
      $fieldPlugin = $this->entity->getFieldMapping($value);
      $subFormState = SubformState::createForSubform(
          $form['field_mappings_wrapper']['field_mappings'][$i],
          $form,
          $formState
      );
      $fieldPlugin->submitConfigurationForm($form['field_mappings_wrapper']['field_mappings'][$i], $subFormState);

      $value = $value + $value['config'] + ['id' => $i];
      unset($value['config'], $value['ops']);
    }
    $this->entity->set('field_mappings', $values['field_mappings']);
    parent::submitForm($form, $formState);
  }

  /**
   * Ajax callback for adding a new field.
   *
   * @param array $form
   *   The current state of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function fieldAddCallback(array &$form, FormStateInterface $formState) {
    return $form['field_mappings_wrapper']['field_mappings'];
  }

  /**
   * Add a field.
   *
   * @param array $form
   *   The current state of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function addField(array &$form, FormStateInterface $formState): void {
    $trigger = $formState->getTriggeringElement();
    $values = &$formState->getValues();
    $newField = NestedArray::getValue($values, ['buttons', 'field_type']);
    if (in_array('add', $trigger['#array_parents'])
      && !empty($newField)
      && $trigger['#name'] != 'context_drupal_field_value'
    ) {
      NestedArray::setValue($values, ['buttons', 'new_field'], $newField);
    }
    $formState->setRebuild(TRUE);
  }

  /**
   * Get an array of drupal types.
   *
   * @param mixed $mapping
   *   The mapping.
   *
   * @return array
   *   Array of type options.
   */
  protected function getDrupalTypeOptions(mixed $mapping): array {
    $fieldPlugins = $this->mappingFieldPluginManager->getDefinitions();
    $options = [];
    foreach ($fieldPlugins as $definition) {
      if (call_user_func([$definition['class'], 'isAllowed'], $mapping)) {
        $options[$definition['id']] = $definition['label'];
      }
    }
    return $options;
  }

  /**
   * Get a field plugin of the given type.
   *
   * @param string $fieldType
   *   The field type.
   *
   * @return mixed
   *   The field plugin.
   */
  protected function getFieldPlugin(string $fieldType): mixed {
    $fieldPlugins = $this->mappingFieldPluginManager->getDefinitions();
    return $fieldPlugins[$fieldType];
  }

}
