<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\DataFetcherInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for properties plugins.
 */
abstract class PropertiesBase extends ApiSyncMappingFieldPluginBase {

  /**
   * Data fetcher service.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected DataFetcherInterface $dataFetcher;

  /**
   * Constructor for PropertiesBase objects.
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
   * @param \Drupal\typed_data\DataFetcherInterface $dataFetcher
   *   Data fetcher service.
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
      SelectionPluginManagerInterface $selectionPluginManager,
      DataFetcherInterface $dataFetcher
  ) {
    parent::__construct(
        $configuration,
        $pluginId,
        $pluginDefinition,
        $entityTypeBundleInfo,
        $entityFieldManager,
        $apiSyncClient,
        $entityTypeManager,
        $dateFormatter,
        $eventDispatcher,
        $selectionPluginManager
    );
    $this->dataFetcher = $dataFetcher;
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
        $container->get('plugin.manager.entity_reference_selection'),
        $container->get('typed_data.data_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::validateConfigurationForm($form, $formState);
    $vals = $formState->getValues();
    $config = $vals['config'];
    if (empty($config['apisync_field'])) {
      $formState->setError($form['config']['apisync_field'], $this->t('API Sync field is required.'));
    }
    if (empty($config['drupal_field_value'])) {
      $formState->setError($form['config']['drupal_field_value'], $this->t('Drupal field is required.'));
    }
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
   * {@inheritdoc}
   *
   * @return string
   *   The outbound value.
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): string {
    // No error checking here. If a property is not defined, it's a
    // configuration bug that needs to be solved elsewhere.
    // Multipicklist is the only target type that handles multi-valued fields.
    $describe = $this
      ->apiSyncClient
      ->objectDescribe($mapping->getApiSyncObjectType());

    $fieldDefinition = $describe['fields'][$this->config('apisync_field')];
    if (($fieldDefinition['type'] ?? NULL) === 'multipicklist') {
      $data = $this->getDataValue($entity, $this->config('drupal_field_value'));
      if (!empty($data)) {
        $strings = [];
        foreach ($data as $item) {
          $strings[] = $item->getString();
        }
        return implode(';', $strings);
      }
    }
    else {
      return $this->getStringValue($entity, $this->config('drupal_field_value'));
    }
  }

  /**
   * Pull callback for field plugins.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $object
   *   The API Sync Object being pulled.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being mapped.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   If a TypedDataInterface is returned, validate constraints and
   *   use TypedDataManager to set the value on the root entity.
   *   Otherwise, set the value directly via FieldableEntityInterface::set.
   */
  public function pullValue(ODataObjectInterface $object, EntityInterface $entity, ApiSyncMappingInterface $mapping): ?TypedDataInterface { // phpcs:ignore
    $fieldSelector = $this->config('drupal_field_value');
    $pullValue = parent::pullValue($object, $entity, $mapping);
    try {
      // Fetch the TypedData property and set its value.
      $data = $this->dataFetcher->fetchDataByPropertyPath($entity->getTypedData(), $fieldSelector);
      $data->setValue($pullValue);
      return $data;
    }
    catch (MissingDataException | InvalidArgumentException $e) {

    }

    // Allow any other exception types to percolate.
    // If the entity doesn't have any value in the field, data fetch will
    // throw an exception. We must attempt to create the field.
    // Typed Data API doesn't provide any good way to initialize a field value
    // given a selector. Instead we have to do it ourselves.
    // We descend only to the first-level fields on the entity. Cascading pull
    // values to entity references is not supported.
    $parts = explode('.', $fieldSelector, 4);

    switch (count($parts)) {
      case 1:
        $entity->set($fieldSelector, $pullValue);
        return $entity->getTypedData()->get($fieldSelector);

      case 2:
        $fieldName = $parts[0];
        $delta = 0;
        $property = $parts[1];
        break;

      case 3:
        $fieldName = $parts[0];
        $delta = $parts[1];
        $property = $parts[2];
        if (!is_numeric($delta)) {
          return NULL;
        }
        break;

      case 4:
        return NULL;

    }

    $listData = $entity->get($fieldName);
    // If the given delta has not been initialized, initialize it.
    if (!$listData->get($delta) instanceof TypedDataInterface) {
      $listData->set($delta, []);
    }

    /** @var \Drupal\Core\TypedData\TypedDataInterface|\Drupal\Core\TypedData\ComplexDataInterface $typedData */
    $typedData = $listData->get($delta);
    if ($typedData instanceof ComplexDataInterface && $property) {
      // If the given property has not been initialized, initialize it.
      if (!$typedData->get($property) instanceof TypedDataInterface) {
        $typedData->set($property, []);
      }
      $typedData = $typedData->get($property);
    }

    if (!$typedData instanceof TypedDataInterface) {
      return NULL;
    }
    $typedData->setValue($pullValue);
    return $typedData->getParent();
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldDataDefinition(EntityInterface $entity): DataDefinitionInterface {
    if (!strpos($this->config('drupal_field_value'), '.')) {
      return parent::getFieldDataDefinition($entity);
    }
    $dataDefinition = $this->dataFetcher->fetchDefinitionByPropertyPath(
        $entity->getTypedData()->getDataDefinition(),
        $this->config('drupal_field_value')
    );
    if ($dataDefinition instanceof ListDataDefinitionInterface) {
      $dataDefinition = $dataDefinition->getItemDefinition();
    }
    return $dataDefinition;
  }

  /**
   * Helper Method to check for and retrieve field data.
   *
   * If it is just a regular field/property of the entity, the data is
   * retrieved with ->value(). If this is a property referenced using the
   * typed_data module's extension, use typed_data module's DataFetcher class
   * to retrieve the value.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search the Typed Data for.
   * @param string $drupalFieldValue
   *   The Typed Data property to get.
   *
   * @return string|null
   *   The String representation of the Typed Data property value.
   */
  protected function getStringValue(EntityInterface $entity, string $drupalFieldValue): ?string {
    try {
      $data = $this->getDataValue($entity, $drupalFieldValue);
      return empty($data) ? NULL : $data->getString();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Another helper Method to check for and retrieve field data.
   *
   * Same as static::getStringValue(), but returns the typed
   * data prior to stringifying.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search the Typed Data for.
   * @param string $drupalFieldValue
   *   The Typed Data property to get.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   *   The array representation of the Typed Data property value.
   */
  protected function getDataValue(EntityInterface $entity, string $drupalFieldValue): TypedDataInterface|null {
    try {
      return $this->dataFetcher->fetchDataByPropertyPath($entity->getTypedData(), $drupalFieldValue);
    }
    catch (\Exception $e) {
      return NULL;
    }
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

}
