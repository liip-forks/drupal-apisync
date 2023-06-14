<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\apisync\Exception\ConfigurationException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the api sync mapped object type entity type.
 *
 * @ConfigEntityType(
 *   id = "apisync_mapped_object_type",
 *   label = @Translation("API Sync Mapped Object Type"),
 *   handlers = {
 *     "storage" = "Drupal\apisync_mapping\ApiSyncMappedObjectTypeStorage",
 *     "list_builder" = "Drupal\apisync_mapping\ApiSyncMappedObjectTypeListBuilder",
 *     "access" = "Drupal\apisync_mapping\ApiSyncMappedObjectTypeAccessController",
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *      },
 *   },
 *   config_prefix = "apisync_mapped_object_type",
 *   admin_permission = "administer apisync mapped object type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "field_mappings",
 *   },
 *   bundle_of = "apisync_mapped_object"
 * )
 */
class ApiSyncMappedObjectType extends ConfigEntityBase implements ApiSyncMappedObjectTypeInterface {

  use StringTranslationTrait;

  protected const EDM_DATA_TYPES = [
    'Edm.Binary',
    'Edm.Boolean',
    'Edm.Byte',
    'Edm.Date',
    'Edm.DateTimeOffset',
    'Edm.Decimal',
    'Edm.Double',
    'Edm.Guid',
    'Edm.Int16',
    'Edm.Int32',
    'Edm.Int64',
    'Edm.SByte',
    'Edm.Single',
    'Edm.String',
    'Edm.TimeOfDay',
  ];

  /**
   * The API Sync mapped object type ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The API Sync mapped object type label.
   *
   * @var string
   */
  protected string $label;


  /**
   * The API Sync mapped object type description.
   *
   * @var string
   */
  protected string $description;

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings(): array {
    $fieldMappings = $this->get('field_mappings') ?? [];
    // Key fields should be sorted by id. This is important as the hash will
    // change if the order changes.
    usort($fieldMappings, static fn($a, $b) => $a['id'] <=> $b['id']);
    return $fieldMappings;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyFieldMappings(): array {
    return array_filter($this->getFieldMappings(), static fn(array $mapping) => (bool) $mapping['is_key']);
  }

  /**
   * {@inheritdoc}
   */
  public function getNonKeyFieldMappings(): array {
    return array_filter($this->getFieldMappings(), static fn(array $mapping) => !$mapping['is_key']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappingViolations(): array {
    $violations = [];
    $fieldMappings = $this->getFieldMappings();
    $keyFieldMapings = $this->getKeyFieldMappings();
    $countKeyFields = count($keyFieldMapings);

    // There is at least one key.
    if ($countKeyFields < 1) {
      $violations[] = (string) $this->t('There must be at least one field marked as a key.');
    }

    // No key can map to apisync_id if there are multiple keys as this must
    // store a hash of the composite keys.
    if ($countKeyFields > 1) {
      foreach ($fieldMappings as $fieldMapping) {
        if ($fieldMapping['drupal_field'] === 'apisync_id') {
          $violations[] = (string) $this->t(
              'No field may map directly to apisync_id if there are multiple keys (apisync_field: @apisync_field)',
              ['@apisync_field' => $fieldMapping['apisync_field']]
          );
        }
      }
    }
    $countFieldMappings = count($fieldMappings);
    // Each remote field must occur only once.
    if (count(array_unique(array_map(
        static fn($fieldMapping) => $fieldMapping['apisync_field'],
        $fieldMappings
    ))) !== $countFieldMappings) {
      $violations[] = (string) $this->t('Each remote field must be unique.');
    }

    // Each drupal field must occur only once.
    if (count(array_unique(array_map(
        static fn($fieldMapping) => $fieldMapping['drupal_field'],
        $fieldMappings
    ))) !== $countFieldMappings) {
      $violations[] = (string) $this->t('Each Drupal field must be unique.');
    }

    // Each item must have a unique ID.
    if (count(array_unique(array_map(
        static fn($fieldMapping) => $fieldMapping['id'],
        $fieldMappings
    ))) !== $countFieldMappings) {
      $violations[] = (string) $this->t('Each ID must be unique.');
    }

    // Each item must have defined an EDM Data Type in apisync_field_type.
    foreach ($fieldMappings as $fieldMapping) {
      if (!isset($fieldMapping['apisync_field_type'])
          || !in_array($fieldMapping['apisync_field_type'], static::EDM_DATA_TYPES, TRUE)
      ) {
        $violations[] = (string) $this->t(
            'The apisync_field_type for field @apisync_field must define an EDM data type. Allowed values: @allowed',
            [
              '@apisync_field' => $fieldMapping['apisync_field'] ?? '***apisync field undefined***',
              '@allowed' => implode(',', static::EDM_DATA_TYPES),
            ]
        );
      }
    }
    if (count(array_unique(array_map(
        static fn($fieldMapping) => $fieldMapping['id'],
        $fieldMappings
    ))) !== $countFieldMappings) {
      $violations[] = (string) $this->t('Each ID must be unique.');
    }
    // Non-key fields cannot map to apisync_id.
    foreach ($this->getNonKeyFieldMappings() as $nonKeyFieldMapping) {
      if ($nonKeyFieldMapping['drupal_field'] === 'apisync_id') {
        $violations[] = (string) $this->t('A non key field may not map to the apisync_id.');
      }
    }

    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function apiSyncIdShallBeHashed(): bool {
    foreach ($this->getFieldMappings() as $field) {
      if ($field['drupal_field'] === 'apisync_id') {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    // This will presently prevent us adding a new mapping in the UI as
    // presently we don't have the needed form components don't have the
    // needed form components.
    $fieldMappingViolations = $this->getFieldMappingViolations();
    if (!empty($fieldMappingViolations)) {
      throw new ConfigurationException('The form fields are not valid: ' . implode(' ', $fieldMappingViolations));
    }
    parent::preSave($storage);
  }

}
