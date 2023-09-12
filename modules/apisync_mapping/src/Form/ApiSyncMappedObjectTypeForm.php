<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Form;

use Drupal\apisync\OData\ODataClientInterface;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API Sync Mapped Object Type form.
 *
 * @property \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface $entity
 */
class ApiSyncMappedObjectTypeForm extends BundleEntityFormBase {

  /**
   * OData client service.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $oDataClient;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Entity definition update manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructs a ApiSyncMappedObjectTypeForm object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $oDataClient
   *   The OData client service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager
   *   The entity definition update manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
      ODataClientInterface $oDataClient,
      EntityTypeBundleInfoInterface $entityTypeBundleInfo,
      EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
      EntityFieldManagerInterface $entityFieldManager,
  ) {
    $this->oDataClient = $oDataClient;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityDefinitionUpdateManager = $entityDefinitionUpdateManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('apisync.odata_client'),
        $container->get('entity_type.bundle.info'),
        $container->get('entity.definition_update_manager'),
        $container->get('entity_field.manager'),
    );
  }

  /**
   * Gets the actual form array to be built.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array
   *   The updated form array.
   */
  public function form(array $form, FormStateInterface $formState): array {

    $form = parent::form($form, $formState);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the api sync mapped object type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\apisync_mapping\Entity\ApiSyncMappedObjectType::load',
      ],
      '#disabled' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the api sync mapped object type.'),
    ];

    $form['field_mappings_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mapped Fields'),
    ];

    $form['field_mappings_wrapper']['field_mappings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#prefix' => '<div id="field-mappings">',
      '#suffix' => '</div>',
    ];

    $apisyncFieldOptions = $this->getApiSyncFieldOptions();

    foreach ($this->entity->getFieldMappings() as $fieldMapping) {
      $fieldset = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Mapping #@id', ['@id' => $fieldMapping['id']]),
        '#disabled' => !$this->entity->isNew() && $fieldMapping['is_key'],
        'config' => [],
      ];

      $fieldset['id'] = [
        '#type' => 'value',
        '#value' => $fieldMapping['id'],
      ];

      $fieldset['drupal_field'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Drupal field'),
        '#machine_name' => [
          'exists' => '\Drupal\apisync_mapping_ui\Form\ApiSyncMappedObjectTypeForm::drupalFieldExists',
        ],
        '#description' => $this->t('A machine readable name for the field to map to.'),
        '#default_value' => $fieldMapping['drupal_field'],
        '#required' => TRUE,
      ];

      $fieldset['apisync_field'] = [
        '#type' => 'select',
        '#title' => $this->t('API Sync field'),
        '#description' => $this->t('Select a API Sync field to map.'),
        '#options' => $apisyncFieldOptions,
        '#default_value' => $fieldMapping['apisync_field'],
        '#empty_option' => $this->t('- Select -'),
        '#required' => TRUE,
      ];

      $fieldset['is_key'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Is key'),
        '#default_value' => $fieldMapping['is_key'],
        '#disabled' => !$this->entity->isNew(),
      ];

      $fieldset['description'] = [
        '#title' => $this->t('Description'),
        '#type' => 'textarea',
        '#description' => $this->t('Details about this field mapping.'),
        '#default_value' => $fieldMapping['description'],
      ];

      $fieldset['actions'] = [
        '#type' => 'actions',
      ];

      $fieldset['actions']['delete'] = [
        '#type' => 'submit',
        '#name' => $fieldMapping['id'],
        '#value' => $this->t('Remove field mapping'),
        '#submit' => ['::removeFieldMapping'],
        '#ajax' => [
          'callback' => [$this, 'fieldMappingAddCallback'],
          'wrapper' => 'field-mappings',
        ],
        '#attributes' => [
          'class' => ['button--danger'],
        ],
      ];

      $form['field_mappings_wrapper']['field_mappings'][] = $fieldset;
    }

    $addFieldText = !empty($this->entity->getFieldMappings())
      ? $this->t('Add another field mapping')
      : $this->t('Add a field mapping to get started');

    $form['field_mappings_wrapper']['add_field_mapping'] = [
      '#type' => 'submit',
      '#value' => $addFieldText,
      '#submit' => ['::addFieldMapping'],
      '#ajax' => [
        'callback' => [$this, 'fieldMappingAddCallback'],
        'wrapper' => 'field-mappings',
      ],
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * Helper to retreive a list of fields for a given object type.
   *
   * @return array
   *   An array of values keyed by machine name of the field with the label as
   *   the value, formatted to be appropriate as a value for #options.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  protected function getApiSyncFieldOptions(): array {
    $options = [];
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
    $mapping = $this->entityTypeManager->getStorage('apisync_mapping')->load($this->entity?->id() ?? '');
    if ($mapping === NULL) {
      throw new NotFoundHttpException('No mapping found with ID ' . $this->entity->id());
    }

    $describe = $this->oDataClient->objectDescribe($mapping->getApiSyncObjectType());
    if (!empty($describe['fields'])) {
      foreach ($describe['fields'] as $key => $field) {
        $options[$key] = $field['Name'];
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): int {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
    $mapping = $this->entityTypeManager->getStorage('apisync_mapping')->load($this->entity?->id() ?? '');
    if ($mapping === NULL) {
      throw new NotFoundHttpException('No mapping found with ID ' . $this->entity->id());
    }
    $describe = $this->oDataClient->objectDescribe($mapping->getApiSyncObjectType());

    // Add apisync_field_type to field mappings.
    $fieldMappings = $this->entity->get('field_mappings');
    foreach ($fieldMappings as &$fieldMapping) {
      $fieldMapping['apisync_field_type'] = $describe['fields'][$fieldMapping['apisync_field']]['Type'];
      unset($fieldMapping['actions']);
    }
    $this->entity->set('field_mappings', $fieldMappings);

    $result = parent::save($form, $formState);

    // Invalidate bundle info cache, if a new mapped object type is created.
    if ($result == SAVED_NEW) {
      $this->entityTypeBundleInfo->clearCachedBundles();
    }

    // Create field definition for field mappings.
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('apisync_mapped_object', $this->entity->id());
    $fieldStorageConfigStorage = $this->entityTypeManager->getStorage('field_storage_config');

    foreach ($fieldMappings as &$fieldMapping) {
      if (!array_key_exists($fieldMapping['drupal_field'], $fieldDefinitions)) {
        if ($fieldStorageConfigStorage->load('apisync_mapped_object.' . $fieldMapping['drupal_field']) === NULL) {
          // Create new field storage for drupal field.
          // @todo Should we consider the type of the fields?
          $fieldStorage = $fieldStorageConfigStorage->create([
            'field_name' => $fieldMapping['drupal_field'],
            'entity_type' => 'apisync_mapped_object',
            'type' => 'string',
            'translatable' => TRUE,
          ]);
          $fieldStorage->save();
        }

        // Create new drupal field for bundle.
        $field = $this->entityTypeManager->getStorage('field_config')->create([
          'field_name' => $fieldMapping['drupal_field'],
          'entity_type' => 'apisync_mapped_object',
          'bundle' => $this->entity->id(),
          'label' => $fieldMapping['apisync_field'],
          'translatable' => FALSE,
        ]);
        $field->save();
      }
    }

    $messageArgs = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new api sync mapped object type %label.', $messageArgs)
      : $this->t('Updated api sync mapped object type %label.', $messageArgs);
    $this->messenger()->addStatus($message);
    $formState->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    $button = $formState->getTriggeringElement();
    if ($button['#id'] != $form['actions']['submit']['#id']) {
      // Skip validation unless we hit the "save" button.
      // Required field erros would otherwise block ajax.
      $formState->clearErrors();
      return;
    }

    $fieldMappings = $this->entity->get('field_mappings');
    foreach ($fieldMappings as &$fieldMapping) {
      // Set apisync_field_type to a temporary value so validation doesn't fail.
      if (empty($fieldMapping['apisync_field_type'])) {
        $fieldMapping['apisync_field_type'] = 'Edm.String';
      }
    }
    $this->entity->set('field_mappings', $fieldMappings);

    $fieldMappingViolations = $this->entity->getFieldMappingViolations();
    if (!empty($fieldMappingViolations)) {
      $formState->setErrorByName('field_mappings_wrapper', $fieldMappingViolations[0]);
    }

    parent::validateForm($form, $formState);
  }

  /**
   * Ajax callback for adding a new field.
   *
   * @param array $form
   *   The current state of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function fieldMappingAddCallback(array &$form, FormStateInterface $formState) {
    return $form['field_mappings_wrapper']['field_mappings'];
  }

  /**
   * Add a field mapping to the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function addFieldMapping(array $form, FormStateInterface $formState): void {
    $fieldMappings = $this->entity->get('field_mappings');
    $fieldMappings[] = [
      'drupal_field' => '',
      'apisync_field' => '',
      'apisync_field_type' => '',
      'id' => end($this->entity->getFieldMappings())['id'] + 1 ?? 0,
      'is_key' => FALSE,
      'description' => '',
    ];
    $this->entity->set('field_mappings', $fieldMappings);

    $formState->setRebuild(TRUE);
  }

  /**
   * Remove a field mapping to the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function removeFieldMapping(array $form, FormStateInterface $formState): void {
    $buttonClicked = $formState->getTriggeringElement()['#name'];
    $fieldMappings = $this->entity->get('field_mappings');
    // Removes the field mapping with the ID from the clicked button.
    $fieldMappings = array_filter(
        $fieldMappings,
        static fn (array $field) => $field['id'] !== $buttonClicked
    );
    $this->entity->set('field_mappings', $fieldMappings);

    $formState->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $routeMatch, $entityTypeId) {
    if ($routeMatch->getRawParameter('mapping_id')) {
      // Create new mapped object type entity with field mappings if
      // route param is set.
      $mappingId = $routeMatch->getParameter('mapping_id');

      /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
      $mapping = $this->entityTypeManager->getStorage('apisync_mapping')->load($mappingId);

      if ($mapping === NULL) {
        throw new NotFoundHttpException('No mapping found with ID ' . $mappingId);
      }

      if ($mapping->getRelatedApiSyncMappedObjectType()) {
        return $mapping->getRelatedApiSyncMappedObjectType();
      }

      $describe = $this->oDataClient->objectDescribe($mapping->getApiSyncObjectType());
      $keyFields = array_filter($describe['fields'], static fn (array $field) => (bool) $field['Key']);

      $fieldMappings = [];
      foreach ($keyFields as $field) {

        $fieldMappings[] = [
          'drupal_field' => count($keyFields) === 1 ? 'apisync_id' : 'field_' . strtolower($field['Name']),
          'apisync_field' => $field['Name'],
          'apisync_field_type' => $field['Type'],
          'id' => count($fieldMappings),
          'is_key' => TRUE,
          'description' => $this->t('Automatically added key field mapping.'),
        ];
      }

      $mappedObjectTypeStorage = $this->entityTypeManager->getStorage('apisync_mapped_object_type');
      return $mappedObjectTypeStorage->create([
        'id' => $mapping->id(),
        'label' => $mapping->label(),
        'field_mappings' => $fieldMappings,
      ]);
    }

    return parent::getEntityFromRouteMatch($routeMatch, $entityTypeId);
  }

  /**
   * Placeholder function for MachineName field validation.
   *
   * @return false
   *   Always return FALSE, so MachineName field validation passes.
   *   Actual validation is done in "validateForm" function.
   */
  public static function drupalFieldExists(): bool {
    return FALSE;
  }

}
