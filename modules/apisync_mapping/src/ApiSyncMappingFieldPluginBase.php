<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncWarningEvent;
use Drupal\apisync\Exception\Exception as ApiSyncException;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a base API Sync Mapping Field Plugin implementation.
 *
 * Extenders need to implement ApiSyncMappingFieldPluginInterface::value()
 * and PluginFormInterface::buildConfigurationForm().
 *
 * @see \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface
 * @see \Drupal\Core\Plugin\PluginFormInterface
 */
abstract class ApiSyncMappingFieldPluginBase extends PluginBase implements ApiSyncMappingFieldPluginInterface {

  /**
   * The label of the mapping.
   *
   * @var string
   */
  protected string $label;

  /**
   * The machine name of the mapping.
   *
   * @var string
   */
  protected string $id;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * API Sync client service.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $apiSyncClient;

  /**
   * Storage handler for API Sync mappings.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Storage handler for Mapped Objects.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The mapping to which this instance is attached.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected ApiSyncMappingInterface $mapping;

  /**
   * Selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected SelectionPluginManagerInterface $selectionPluginManager;

  /**
   * Constructor for a ApiSyncMappingFieldPluginBase object.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $pluginId
   *   Plugin id.
   * @param array $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   * @param \Drupal\apisync\OData\ODataClientInterface $apiSyncClient
   *   API Sync client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   ETM service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionPluginManager
   *   Selection plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      array $configuration,
      $pluginId,
      array $pluginDefinition,
      EntityTypeBundleInfoInterface $entityTypeBundleInfo,
      EntityFieldManagerInterface $entityFieldManager,
      ODataClientInterface $apiSyncClient,
      EntityTypeManagerInterface $entityTypeManager,
      DateFormatterInterface $dateFormatter,
      EventDispatcherInterface $eventDispatcher,
      SelectionPluginManagerInterface $selectionPluginManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    if (!empty($configuration['mapping'])) {
      $this->mapping = $configuration['mapping'];
    }
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
    $this->apiSyncClient = $apiSyncClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->mappingStorage = $entityTypeManager->getStorage('apisync_mapping');
    $this->mappedObjectStorage = $entityTypeManager->getStorage('apisync_mapped_object');
    $this->dateFormatter = $dateFormatter;
    $this->eventDispatcher = $eventDispatcher;
    $this->selectionPluginManager = $selectionPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      array $configuration,
      $pluginId,
      $pluginDefinition
  ): static {
    return new static(
        $configuration,
        $pluginId,
        $pluginDefinition,
        $container->get('entity_type.bundle.info'),
        $container->get('entity_field.manager'),
        $container->get('apisync.odata_client'),
        $container->get('entity_type.manager'),
        $container->get('date.formatter'),
        $container->get('event_dispatcher'),
        $container->get('plugin.manager.entity_reference_selection')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(ApiSyncMappingInterface $mapping): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function pushValue(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed {
    // If this field plugin doesn't support apisync_field config type, or
    // doesn't do push, then return the raw value from the mapped entity.
    $value = $this->value($entity, $mapping);
    if (!$this->push() || empty($this->config('apisync_field'))) {
      return $value;
    }

    // objectDescribe can throw an exception, but that's outside the scope of
    // being handled here. Allow it to percolate.
    $describe = $this
      ->apiSyncClient
      ->objectDescribe($mapping->getApiSyncObjectType());

    try {
      // $fieldDefinition = $describe->getField($this->config('apisync_field'));
      $fieldDefinition = $describe['fields'][$this->config('apisync_field')] ?? [];
      if (empty($fieldDefinition)) {
        throw new \Exception('Field not found.');
      }
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(
          new ApiSyncWarningEvent(
              $e,
              'Field definition not found for %describe.%field',
              [
                '%describe' => $describe['name'],
                '%field' => $this->config('apisync_field'),
              ]
          ),
          ApiSyncEvents::WARNING
      );
      // If getField throws, however, just return the raw value.
      return $value;
    }
    switch (strtolower($fieldDefinition['type'] ?? '')) {
      case 'boolean':
        if ($value == 'false') {
          $value = FALSE;
        }
        $value = (bool) $value;
        break;

      case 'date':
      case 'datetime':
        if (!empty($value)) {
          $date = new DrupalDateTime($value, 'UTC');
          $value = $date->format(\DateTime::ATOM);
        }
        break;

      case 'double':
        $value = (double) $value;
        break;

      case 'integer':
        $value = (int) $value;
        break;

      case 'multipicklist':
        if (is_array($value)) {
          $value = implode(';', $value);
        }
        break;

      case 'id':
      case 'reference':
        if (empty($value)) {
          break;
        }
        $value = (string) $value;
        break;
    }

    if (isset($fieldDefinition['length'])
        && $fieldDefinition['length'] > 0
        && mb_strlen($value) > $fieldDefinition['length']
    ) {
      $value = mb_substr($value, 0, $fieldDefinition['length']);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\apisync\Exception\Exception
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

    // objectDescribe can throw an exception, but that's outside the scope of
    // being handled here. Allow it to percolate.
    $describe = $this
      ->apiSyncClient
      ->objectDescribe($mapping->getApiSyncObjectType());

    $dataDefinition = $this->getFieldDataDefinition($entity);
    $drupalFieldType = $this->getDrupalFieldType($dataDefinition);
    $drupalFieldSettings = $dataDefinition->getSettings();

    $fieldDefinition = $describe['fields'][$this->config('apisync_field')];
    switch (strtolower($fieldDefinition['Type'])) {
      case 'Edm.Boolean':
        if (is_string($value) && strtolower($value) === 'false') {
          $value = FALSE;
        }
        $value = (bool) $value;
        break;

      case 'Edm.DateTime':
      case 'Edm.Time':
      case 'Edm.Date':
        if ($drupalFieldType === 'datetime_iso8601') {
          $value = substr($value, 0, 19);
        }
        break;

      case 'Edm.Double':
        $value = $value === NULL ? $value : (double) $value;
        break;

      case 'Edm.Int32':
        $value = $value === NULL ? $value : (int) $value;
        break;

      default:
        if (is_string($value)
            && isset($drupalFieldSettings['max_length'])
            && $drupalFieldSettings['max_length'] > 0
            && $drupalFieldSettings['max_length'] < mb_strlen($value)
        ) {
          $value = mb_substr($value, 0, $drupalFieldSettings['max_length']);
        }
        break;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   *
   * In order to set a config value to null, use setConfiguration()
   *
   * @return mixed
   *   The config value.
   */
  public function config(?string $key = NULL, $value = NULL): mixed {
    if ($key === NULL) {
      return $this->configuration;
    }
    if ($value !== NULL) {
      $this->configuration[$key] = $value;
    }
    if (array_key_exists($key, $this->configuration)) {
      return $this->configuration[$key];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'direction' => MappingConstants::APISYNC_MAPPING_DIRECTION_SYNC,
      'apisync_field' => [],
      'drupal_field_type' => $this->getPluginId(),
      'drupal_field_value' => '',
      'mapping_id' => '',
      'description' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = [];
    $pluginDefinition = $this->getPluginDefinition();

    // Extending plugins will probably inject most of their own logic here:
    $pluginForm['drupal_field_value'] = [
      '#title' => $pluginDefinition['label'],
    ];

    $options = $this->getApiSyncFieldOptions($form['#entity']->getApiSyncObjectType());
    $pluginForm['apisync_field'] = [
      '#title' => $this->t('API Sync field'),
      '#type' => 'select',
      '#description' => $this->t('Select a API Sync field to map.'),
      '#options' => $options,
      '#default_value' => $this->config('apisync_field'),
      '#empty_option' => $this->t('- Select -'),
    ];

    $pluginForm['direction'] = [
      '#title' => $this->t('Direction'),
      '#type' => 'radios',
      '#options' => [
        MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE => $this->t('Drupal to Remote'),
        MappingConstants::APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL => $this->t('Remote to Drupal'),
        MappingConstants::APISYNC_MAPPING_DIRECTION_SYNC => $this->t('Sync'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->config('direction')
      ? $this->config('direction')
      : MappingConstants::APISYNC_MAPPING_DIRECTION_SYNC,
      '#attributes' => ['class' => ['narrow']],
    ];
    $pluginForm['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#description' => $this->t('Details about this field mapping.'),
      '#default_value' => $this->config('description'),
    ];

    return $pluginForm;
  }

  /**
   * Helper for buildConfigurationForm() to build a broken field plugin.
   *
   * @return array
   *   The dummy form with message to indicate the plugin is broken.
   *
   * @see buildConfigurationForm()
   */
  protected function buildBrokenConfigurationForm(array &$pluginForm, FormStateInterface $formState): array {
    foreach ($this->config() as $key => $value) {
      if (!empty($pluginForm[$key])) {
        $pluginForm[$key]['#type'] = 'hidden';
        $pluginForm[$key]['#value'] = $value;
      }
    }

    $pluginForm['drupal_field_type'] = [
      '#type' => 'hidden',
      '#value' => $this->config('drupal_field_type'),
    ];

    return [
      'message' => [
        '#markup' => '<div class="error">'
        . $this->t('The field plugin %plugin is broken or missing.', ['%plugin' => $this->config('drupal_field_type')])
        . '</div>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {

  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function get(?string $key): array|string|null {
    return $this->config($key);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value): void {
    $this->$key = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function push(): bool {
    return in_array($this->config('direction'), [
      MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE,
      MappingConstants::APISYNC_MAPPING_DIRECTION_SYNC,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function pull(): bool {
    return in_array($this->config('direction'), [
      MappingConstants::APISYNC_MAPPING_DIRECTION_SYNC,
      MappingConstants::APISYNC_MAPPING_DIRECTION_REMOTE_DRUPAL,
    ]);
  }

  /**
   * Helper to retreive a list of fields for a given object type.
   *
   * @param string $objectName
   *   The object type of whose fields you want to retreive.
   *
   * @return array
   *   An array of values keyed by machine name of the field with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getApiSyncFieldOptions(string $objectName): array {
    // Static cache since this function is called frequently across many
    // different object instances.
    $options = &drupal_static(__CLASS__ . __FUNCTION__, []);
    if (empty($options[$objectName])) {
      $describe = $this->apiSyncClient->objectDescribe($objectName);
      if (!empty($describe['fields'])) {
        foreach ($describe['fields'] as $key => $field) {
          $options[$objectName][$key] = $field['Name'];
        }
      }
    }
    return $options[$objectName];
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldMappingDependency(array $dependencies): bool {
    // No config dependencies by default.
    return FALSE;
  }

  /**
   * Return TRUE if the given field uses an entity reference handler.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $instance
   *   The field.
   *
   * @return bool
   *   Whether the field is an entity reference.
   */
  protected function instanceOfEntityReference(FieldDefinitionInterface $instance): bool {
    $handler = $instance->getSetting('handler');
    // We must have a handler.
    if (empty($handler)) {
      return FALSE;
    }
    // If the handler is a selection interface, return TRUE.
    $plugin = $this->selectionPluginManager->getSelectionHandler($instance);
    return $plugin instanceof SelectionInterface;
  }

  /**
   * Helper method to get the Data Definition for the current field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The Entity to get the field from.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The Data Definition of the current field.
   */
  protected function getFieldDataDefinition(FieldableEntityInterface $entity): DataDefinitionInterface {
    return $entity->get($this->config('drupal_field_value'))
      ->getFieldDefinition()
      ->getItemDefinition();
  }

  /**
   * Helper method to get the Field Type of the given Field Data Definition.
   *
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface $dataDefinition
   *   The Drupal Field Data Definition object.
   *
   * @return string|null
   *   The Drupal Field Type or NULL.
   */
  protected function getDrupalFieldType(ComplexDataDefinitionInterface $dataDefinition): ?string {
    $fieldMainProperty = $dataDefinition
      ->getPropertyDefinition($dataDefinition->getMainPropertyName());

    return $fieldMainProperty ? $fieldMainProperty->getDataType() : NULL;
  }

}
