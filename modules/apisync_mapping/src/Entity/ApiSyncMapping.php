<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\apisync\Exception\Exception;
use Drupal\apisync\Exception\NotImplementedException as ExceptionNotImplementedException;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\SelectQuery;
use Drupal\apisync\OData\SelectQueryInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a API Sync Mapping configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "apisync_mapping",
 *   label = @Translation("API Sync Mapping"),
 *   module = "apisync_mapping",
 *   handlers = {
 *     "storage" = "Drupal\apisync_mapping\ApiSyncMappingStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\apisync_mapping\ApiSyncMappingAccessController",
 *   },
 *   admin_permission = "administer apisync mapping",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *    "id",
 *    "label",
 *    "weight",
 *    "type",
 *    "async",
 *    "push_standalone",
 *    "pull_standalone",
 *    "pull_trigger_date",
 *    "pull_where_clause",
 *    "sync_triggers",
 *    "apisync_object_type",
 *    "drupal_entity_type",
 *    "drupal_bundle",
 *    "field_mappings",
 *    "push_limit",
 *    "push_retries",
 *    "push_frequency",
 *    "pull_frequency",
 *   },
 *   lookup_keys = {
 *     "drupal_entity_type",
 *     "drupal_bundle",
 *     "apisync_object_type"
 *   }
 * )
 */
class ApiSyncMapping extends ConfigEntityBase implements ApiSyncMappingInterface {

  use StringTranslationTrait;

  /**
   * Only one bundle type for now.
   *
   * @var string
   */
  protected string $type = 'apisync_mapping';

  /**
   * ID (machine name) of the Mapping.
   *
   * @var string
   *
   * @note numeric id was removed
   */
  protected string $id;

  /**
   * Label of the Mapping.
   *
   * @var string
   */
  protected string $label;

  /**
   * A default weight for the mapping.
   *
   * @var int
   */
  protected int $weight = 0;

  /**
   * Whether to push asychronous.
   *
   *   - If true, disable real-time push.
   *   - If false (default), attempt real-time push and enqueue failures for
   *     async push.
   *
   * Note this is different behavior compared to D7.
   *
   * @var bool
   */
  protected bool $async = FALSE;

  /**
   * Whether a standalone push endpoint is enabled for this mapping.
   *
   * @var bool
   */
  protected bool $push_standalone = FALSE;

  /**
   * Whether a standalone push endpoint is enabled for this mapping.
   *
   * @var bool
   */
  protected bool $pull_standalone = FALSE;

  /**
   * The API Sync field to use for determining whether or not to pull.
   *
   * @var string|null
   */
  protected string|null $pull_trigger_date = NULL;

  /**
   * Additional "where" logic to append to pull-polling query.
   *
   * @var string
   */
  protected string $pull_where_clause = '';

  /**
   * The drupal entity type to which this mapping points.
   *
   * @var string
   */
  protected string $drupal_entity_type;

  /**
   * The drupal entity bundle to which this mapping points.
   *
   * @var string
   */
  protected string $drupal_bundle;

  /**
   * The oData object type to which this mapping points.
   *
   * @var string
   */
  protected string $apisync_object_type;


  /**
   * Mapped field plugins.
   *
   * @var array
   */
  protected array $field_mappings = [];

  /**
   * Active sync triggers.
   *
   * @var array
   */
  protected array $sync_triggers = [];

  /**
   * Stateful push data for this mapping.
   *
   * @var array
   */
  protected array $push_info;

  /**
   * Statefull pull data for this mapping.
   *
   * @var array
   */
  protected array $pull_info;

  /**
   * How often (in seconds) to push with this mapping.
   *
   * @var int
   */
  protected int $push_frequency = 0;

  /**
   * Maxmimum number of records to push during a batch.
   *
   * @var int
   */
  protected int $push_limit = 0;

  /**
   * Maximum number of attempts to push a record before it's considered failed.
   *
   * @var int
   */
  protected int $push_retries = 3;

  /**
   * How often (in seconds) to pull with this mapping.
   *
   * @var int
   */
  protected int $pull_frequency = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entityType) {
    parent::__construct($values, $entityType);
    $pushInfo = $this->state()->get('apisync.mapping_push_info', []);
    if (empty($pushInfo[$this->id()])) {
      $pushInfo[$this->id()] = [
        'last_timestamp' => 0,
      ];
    }
    $this->push_info = $pushInfo[$this->id()];

    $pullInfo = $this->state()->get('apisync.mapping_pull_info', []);
    if (empty($pullInfo[$this->id()])) {
      $pullInfo[$this->id()] = [
        'last_pull_timestamp' => 0,
        'last_delete_timestamp' => 0,
      ];
    }
    $this->pull_info = $pullInfo[$this->id()];
    foreach ($this->field_mappings as $i => &$fieldMapping) {
      $fieldMapping['id'] = $i;
      $fieldMapping['mapping'] = $this;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __get(string $key): mixed {
    return $this->$key;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\apisync\Exception\Exception
   */
  public function getRelatedApiSyncMappedObjectType(): ?ApiSyncMappedObjectTypeInterface {
    $bundleInfo = $this->entityTypeBundleInfo()->getBundleInfo('apisync_mapped_object');
    // Make sure mapped object type exsits.
    if (!isset($bundleInfo[$this->id()])) {
      return NULL;
    }
    $apiSyncMappedObjectTypeStorage = $this->entityTypeManager()->getStorage('apisync_mapped_object_type');
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface|null $mappedObjectTypeEntity */
    $mappedObjectTypeEntity = $apiSyncMappedObjectTypeStorage->load($this->id());
    if ($mappedObjectTypeEntity === NULL) {
      throw new Exception(
        sprintf('A mapped object type defined with ID of %s appears to be defined, but could not be loaded.', $this->id())
      );
    }
    return $mappedObjectTypeEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    if (empty($this->field_mappings)) {
      return [];
    }
    return [
      'field_mappings' => new DefaultLazyPluginCollection($this->fieldManager(), $this->field_mappings),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    // Schema API complains during save() if field_mappings' mapping property
    // exists as a reference to the parent mapping. It's redundant anyway, so
    // we can delete it safely.
    $entityArray = parent::toArray();
    foreach ($entityArray['field_mappings'] as &$value) {
      unset($value['mapping']);
    }
    return $entityArray;
  }

  /**
   * {@inheritdoc}
   */
  public function save(): int {
    $this->updated = $this->getRequestTime();
    if (isset($this->is_new) && $this->is_new) {
      $this->created = $this->getRequestTime();
    }
    return parent::save();
  }

  /**
   * Testable func to return the request time server variable.
   *
   * @return int
   *   The request time.
   */
  protected function getRequestTime(): int {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    // Update shared pull values across other mappings to same object type.
    $pullMappings = $storage->loadByProperties([
      'apisync_object_type' => $this->apisync_object_type,
    ]);
    unset($pullMappings[$this->id()]);
    foreach ($pullMappings as $mapping) {
      if ($this->pull_frequency != $mapping->pull_frequency) {
        $mapping->pull_frequency = $this->pull_frequency;
        $mapping->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    // Include config dependencies on all mapped Drupal fields.
    $this->dependencies = array_intersect_key($this->dependencies, ['enforced' => '']);
    foreach ($this->getFieldMappings() as $field) {
      // Configuration entities need to depend on the providers of any plugins
      // that they store the configuration for. Default calculateDependencies()
      // method does not work, because our field_mapping plugins are anonymous,
      // indexed by numeric id only.
      /** @var \Drupal\Component\Plugin\PluginInspectionInterface $field */
      $this->calculatePluginDependencies($field);
    }

    // Add a hard dependency on the mapping entity and bundle.
    $entityType = $this->entityTypeManager()->getDefinition($this->getDrupalEntityType());
    if ($entityType !== NULL) {
      $dependency = $entityType->getBundleConfigDependency($this->getDrupalBundle());
      $this->addDependency($dependency['type'], $dependency['name']);
    }
    if ($this->doesPull()) {
      $this->addDependency('module', 'apisync_pull');
    }
    if ($this->doesPush()) {
      $this->addDependency('module', 'apisync_push');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    parent::onDependencyRemoval($dependencies);

    // If the mapped entity type is being removed, we'll delete this mapping.
    $entityType = $this->entityTypeManager()->getDefinition($this->getDrupalEntityType());
    $dependency = $entityType->getBundleConfigDependency($this->getDrupalBundle());
    if (!empty($dependencies[$dependency['type']][$dependency['name']])) {
      return FALSE;
    }

    // Otherwise, ask each field mapping plugin if wants to remove itself.
    return $this->removePluginDependencies($dependencies);
  }

  /**
   * Delegate dependency removal events to field mappings plugins.
   *
   * @param array $dependencies
   *   Dependencies.
   *
   * @return bool
   *   TRUE if the entity has been changed.
   */
  public function removePluginDependencies(array $dependencies): bool {
    $changed = FALSE;
    foreach ($this->getFieldMappings() as $i => $field) {
      if ($field->checkFieldMappingDependency($dependencies)) {
        $changed = TRUE;
        // If a plugin is dependent on the configuration being deleted, remove
        // the field mapping.
        unset($this->field_mappings[$i]);
      }
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function getPullFields(): array {
    $fields = [];
    foreach ($this->getFieldMappings() as $i => $fieldPlugin) {
      // Skip fields that aren't being pulled from remote.
      if (!$fieldPlugin->pull()) {
        continue;
      }
      $fields[$i] = $fieldPlugin;
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getPullFieldsArray(): array {
    return array_column($this->field_mappings, 'apisync_field', 'apisync_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyField(): string {
    throw new ExceptionNotImplementedException('Upserting is not supported. Method to be removed.');
  }

  /**
   * {@inheritdoc}
   */
  public function hasKey(): bool {
    throw new ExceptionNotImplementedException('Upserting is not supported. Method to be removed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(EntityInterface $entity): mixed {
    throw new ExceptionNotImplementedException('Upserting is not supported. Method to be removed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiSyncObjectType(): string {
    return $this->apisync_object_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalEntityType(): string {
    return $this->drupal_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalBundle(): string {
    return $this->drupal_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings(): array {
    $fields = [];
    foreach ($this->field_mappings as $i => $field) {
      $fields[$i] = $this->fieldManager()->createInstance(
          $field['drupal_field_type'],
          $field + ['mapping' => $this]
      );
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping(array $field): ApiSyncMappingFieldPluginInterface {
    return $this->fieldManager()->createInstance(
        $field['drupal_field_type'],
        $field['config'] + ['mapping' => $this]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPullTriggerDate(): string|null {
    return $this->pull_trigger_date;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPushStandalone(): bool {
    return $this->push_standalone;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPullStandalone(): bool {
    return $this->pull_standalone;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPush(): bool {
    return $this->checkTriggers([
      MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_CREATE,
      MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_UPDATE,
      MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_DELETE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function doesPull(): bool {
    return $this->checkTriggers([
      MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE,
      MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE,
      MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTriggers(array $triggers): bool {
    foreach ($triggers as $trigger) {
      if (!empty($this->sync_triggers[$trigger])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the name of this configuration object.
   *
   * @return string
   *   The name of the configuration object.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTime(): ?int {
    return $this->pull_info['last_delete_timestamp'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastDeleteTime(?int $time): static {
    return $this->setPullInfo('last_delete_timestamp', $time);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastPullTime(): ?int {
    return $this->pull_info['last_pull_timestamp'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastPullTime(?int $time): static {
    return $this->setPullInfo('last_pull_timestamp', $time);
  }

  /**
   * Setter for pull info.
   *
   * @param string $key
   *   The config id to set.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  protected function setPullInfo(string $key, mixed $value): static {
    $this->pull_info[$key] = $value;
    $pullInfo = $this->state()->get('apisync.mapping_pull_info');
    $pullInfo[$this->id()] = $this->pull_info;
    $this->state()->set('apisync.mapping_pull_info', $pullInfo);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextPullTime(): int {
    return $this->pull_info['last_pull_timestamp'] + $this->pull_frequency;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastPushTime(): ?int {
    return $this->push_info['last_timestamp'];
  }

  /**
   * {@inheritdoc}
   */
  public function setLastPushTime(int $time): static {
    return $this->setPushInfo('last_timestamp', $time);
  }

  /**
   * Setter for pull info.
   *
   * @param string $key
   *   The config id to set.
   * @param mixed $value
   *   The value.
   *
   * @return static
   *   The current instance. ($this)
   */
  protected function setPushInfo(string $key, mixed $value): static {
    $this->push_info[$key] = $value;
    $pushInfo = $this->state()->get('apisync.mapping_push_info');
    $pushInfo[$this->id()] = $this->push_info;
    $this->state()->set('apisync.mapping_push_info', $pushInfo);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextPushTime(): int {
    return $this->push_info['last_timestamp'] + $this->push_frequency;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\apisync\Exception\Exception
   */
  public function getPullQuery(array $mappedFields = [], int $start = 0, int $stop = 0): SelectQueryInterface {
    if (!$this->doesPull()) {
      throw new Exception('Mapping does not pull.');
    }
    $objectType = $this->getApiSyncObjectType();
    $query = new SelectQuery($objectType);

    // Convert field mappings to query.
    if (empty($mappedFields)) {
      $mappedFields = $this->getPullFieldsArray();
    }
    $query->setFields($mappedFields);

    $mappedObjectType = $this->getRelatedApiSyncMappedObjectType();

    if ($mappedObjectType === NULL) {
      throw new Exception('No mapped object type found for mapping ' . $this->id());
    }

    // Add all metadata fields.
    foreach ($mappedObjectType->getFieldMappings() as $keyFieldMapping) {
      $query->addField($keyFieldMapping['apisync_field']);
    }
    // Filter by the pull trigger date.
    if ($this->getPullTriggerDate()) {
      $query->addField($this->getPullTriggerDate());
    }
    $start = $start > 0 ? $start : $this->getLastPullTime();
    // If no lastupdate and no start window provided, get all records.
    if ($start && $this->getPullTriggerDate()) {
      $start = gmdate('Y-m-d\TH:i:s\Z', $start);
      $query->addCondition($this->getPullTriggerDate(), $start, '>');
    }

    if ($stop) {
      $stop = gmdate('Y-m-d\TH:i:s\Z', $stop);
      $query->addCondition($this->getPullTriggerDate(), $stop, '<');
    }

    if (!empty($this->pull_where_clause)) {
      $query->addBuiltCondition([$this->pull_where_clause]);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysUpsert(): bool {
    throw new ExceptionNotImplementedException('Upserting is not supported. Method to be removed.');
  }

  /**
   * API Sync Mapping Field Manager service.
   *
   * @return \Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager
   *   The plugin.manager.apisync_mapping_field service.
   */
  protected function fieldManager(): ApiSyncMappingFieldPluginManager {
    return \Drupal::service('plugin.manager.apisync_mapping_field');
  }

  /**
   * API Sync odata client service.
   *
   * @return \Drupal\apisync\OData\ODataClientInterface
   *   The apisync.odata_client service.
   */
  protected function client(): ODataClientInterface {
    return \Drupal::service('apisync.odata_client');
  }

  /**
   * State service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The state service.
   */
  protected function state(): StateInterface {
    return \Drupal::state();
  }

}
