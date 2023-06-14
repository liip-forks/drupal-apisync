<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Entity;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\apisync\Event\ApiSyncWarningEvent;
use Drupal\apisync\Exception\ConfigurationException;
use Drupal\apisync\Exception\NotImplementedException;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\ODataObjectInterface;
use Drupal\apisync_mapping\Event\ApiSyncPullEntityValueEvent;
use Drupal\apisync_mapping\Event\ApiSyncPullEvent;
use Drupal\apisync_mapping\Event\ApiSyncPushParamsEvent;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\apisync_mapping\Plugin\Field\FieldType\ODataLinkItemList;
use Drupal\apisync_mapping\PushActions;
use Drupal\apisync_mapping\PushParams;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a API Sync Mapped Object entity class.
 *
 * Mapped Objects are content entities, since they're defined by references
 * to other content entities.
 *
 * @ContentEntityType(
 *   id = "apisync_mapped_object",
 *   label = @Translation("API Sync Mapped Object"),
 *   label_singular = @Translation("API Sync Mapped Object"),
 *   module = "apisync_mapping",
 *   bundle_label = @Translation("Mapped object type"),
 *   handlers = {
 *     "storage" = "Drupal\apisync_mapping\ApiSyncMappedObjectStorage",
 *     "storage_schema" = "Drupal\apisync_mapping\ApiSyncMappedObjectStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\apisync_mapping\ApiSyncMappedObjectAccessControlHandler",
 *   },
 *   fieldable = TRUE,
 *   translatable = FALSE,
 *   base_table = "apisync_mapped_object",
 *   revision_table = "apisync_mapped_object_revision",
 *   admin_permission = "administer apisync mapping",
 *   entity_keys = {
 *      "id" = "id",
 *      "bundle" = "type",
 *      "entity_id" = "drupal_entity__target_id",
 *      "apisync_id" = "apisync_id",
 *      "revision" = "revision_id",
 *      "label" = "apisync_id"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   bundle_entity_type = "apisync_mapped_object_type",
 *   field_ui_base_route = "entity.apisync_mapped_object_type.edit_form",
 *   constraints = {
 *     "MappingApiSyncId" = {},
 *     "MappingEntity" = {},
 *     "MappingEntityType" = {}
 *   }
 * )
 */
class ApiSyncMappedObject extends RevisionableContentEntityBase implements ApiSyncMappedObjectInterface {

  use EntityChangedTrait;

  /**
   * API Sync Object.
   *
   * @var \Drupal\apisync\OData\ODataObjectInterface|null
   */
  protected ?ODataObjectInterface $oDataRecord = NULL;

  /**
   * Drupal entity stub, as its in the process of being created during pulls.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface|null
   */
  protected ?FieldableEntityInterface $drupalEntityStub = NULL;

  /**
   * Overrides ContentEntityBase::__construct().
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   * @param string $entityType
   *   The type of the entity to create.
   * @param string|false $bundle
   *   The entity bundle, or FALSE if $entityType should be used.
   */
  public function __construct(array $values, string $entityType, string|false $bundle = FALSE) {
    // @todo Revisit this language stuff.
    // Drupal adds a layer of abstraction for translation purposes, even though
    // we're talking about numeric identifiers that aren't language-dependent
    // in any way, so we have to build our own constructor in order to allow
    // callers to ignore this layer.
    foreach ($values as &$value) {
      if (!is_array($value)) {
        $value = [LanguageInterface::LANGCODE_DEFAULT => $value];
      }
    }
    // We pass the bundle to the constructor, as entity form expect it to exist.
    parent::__construct($values, $entityType, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, object $record): void {
    // Revision uid, timestamp, and message are required for D9.
    if ($this->isNewRevision()) {
      if (empty($this->getRevisionUserId())) {
        $this->setRevisionUserId(1);
      }
      if (empty($this->getRevisionCreationTime())) {
        $this->setRevisionCreationTime(time());
      }
      if (empty($this->getRevisionLogMessage())) {
        $this->setRevisionLogMessage('New revision');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(): int {
    $this->changed = $this->getRequestTime();
    if ($this->isNew()) {
      $this->created = $this->getRequestTime();
    }
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    if ($update) {
      $this->pruneRevisions($storage);
    }
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function pruneRevisions(EntityStorageInterface $storage): void {
    $limit = $this
      ->config('apisync.settings')
      ->get('limit_mapped_object_revisions');
    if ($limit <= 0) {
      // Limit 0 means no limit.
      return;
    }
    $count = $storage
      ->getQuery()
      ->allRevisions()
      ->condition('id', $this->id())
      ->count()
      ->execute();

    // Query for any revision id beyond the limit.
    if ($count <= $limit) {
      return;
    }
    $vidsToDelete = $storage
      ->getQuery()
      ->allRevisions()
      ->condition('id', $this->id())
      ->range($limit, $count)
      ->sort('changed', 'DESC')
      ->execute();
    if (empty($vidsToDelete)) {
      return;
    }
    foreach ($vidsToDelete as $vid => $dummy) {
      /** @var \Drupal\Core\Entity\RevisionableInterface $revision */
      $revision = $storage->loadRevision($vid);
      // Prevent deletion if this is the default revision.
      if ($revision && $revision->isDefaultRevision()) {
        continue;
      }
      $storage->deleteRevision($vid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $i = 0;
    $fields['drupal_entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Mapped Entity'))
      ->setDescription(t('Reference to the Drupal entity mapped by this mapped object.'))
      ->setRevisionable(FALSE)
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'dynamic_entity_reference_default',
        'weight' => $i,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'dynamic_entity_reference_label',
        'weight' => $i++,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['apisync_mapping'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Sync mapping'))
      ->setDescription(t('API Sync mapping used to push/pull this mapped object'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setSetting('target_type', 'apisync_mapping')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => $i,
      ])
      ->setSettings([
        'allowed_values' => [
          // API Sync Mappings for this entity type go here.
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    // Asci required see: https://www.drupal.org/node/2510940.
    $fields['apisync_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('API Sync ID'))
      ->setDescription(t('Reference to the mapped OData object'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('is_ascii', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => $i++,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
      ]);

    $fields['apisync_link'] = BaseFieldDefinition::create('apisync_link')
      ->setLabel('OData Record')
      ->setDescription(t('Link to odata record'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setComputed(TRUE)
      ->setClass(ODataLinkItemList::class)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the object mapping was created.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => $i++,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the object mapping was last edited.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['entity_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Drupal Entity Updated'))
      ->setDescription(t('The Unix timestamp when the mapped Drupal entity was last updated.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['last_sync_status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status of most recent sync'))
      ->setDescription(t('Indicates whether most recent sync was successful or not.'))
      ->setRevisionable(TRUE);

    $fields['last_sync_action'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Action of most recent sync'))
      ->setDescription(t('Indicates acion which triggered most recent sync for this mapped object'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', MappingConstants::APISYNC_MAPPING_TRIGGER_MAX_LENGTH)
      ->setRevisionable(TRUE);

    $fields['force_pull'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Force Pull'))
      ->setDescription(t('Whether to ignore entity timestamps and force an update on the next pull for this record.'))
      ->setRevisionable(FALSE);

    // @see ContentEntityBase::baseFieldDefinitions
    // and RevisionLogEntityTrait::revisionLogBaseFieldDefinitions
    $fields += parent::baseFieldDefinitions($entityType);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getChanged(): int {
    return (int) $this->get('entity_updated')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMapping(): ApiSyncMappingInterface {
    return $this->apisync_mapping->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedEntity(): FieldableEntityInterface {
    return $this->drupal_entity->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setDrupalEntity(?FieldableEntityInterface $entity = NULL): static {
    $this->set('drupal_entity', $entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function client(): ODataClientInterface {
    return \Drupal::service('apisync.odata_client');
  }

  /**
   * {@inheritdoc}
   */
  public function eventDispatcher(): EventDispatcherInterface {
    return \Drupal::service('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function config(mixed $name): mixed {
    return \Drupal::service('config.factory')->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function authManager(): ApiSyncAuthProviderPluginManagerInterface {
    return \Drupal::service('plugin.manager.apisync.auth_providers');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiSyncUrl(): string {
    return $this->authManager()->getProvider()->getInstanceUrl() . $this->getApiSyncPath();
  }

  /**
   * {@inheritdoc}
   */
  public function getApiSyncPath(): string {
    $objectType = $this->getMapping()->getApiSyncObjectType();
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface $mappedObjectType */
    $mappedObjectType = $this->entityTypeManager()->getStorage('apisync_mapped_object_type')->load($this->bundle());
    $nameValueComponents = array_map(function (array $keyFieldMapping) {
      $fieldName = $keyFieldMapping['apisync_field'];
      $fieldValue = $this->get($keyFieldMapping['drupal_field'])->getString();
      // Strings need to be enclosed in quotes.
      if ($keyFieldMapping['apisync_field_type'] === 'Edm.String') {
        $fieldValue = "'$fieldValue'";
      }
      return "$fieldName=$fieldValue";
    }, $mappedObjectType->getKeyFieldMappings());
    $paramsString = implode(',', $nameValueComponents);
    return "/{$objectType}({$paramsString})";
  }

  /**
   * {@inheritdoc}
   */
  public function apiSyncId(): ?string {
    return $this->apisync_id->value;
  }

  /**
   * {@inheritdoc}
   */
  public function push(): mixed {
    // @todo Needs error handling, logging, and hook invocations within this function,
    // where we can provide full context, or short of that clear documentation
    // on how callers should handle errors and exceptions. At the very least,
    // we need to make sure to include $params in some kind of exception if
    // we're not going to handle it inside this function.
    $mapping = $this->getMapping();
    $drupalEntity = $this->getMappedEntity();

    // Allows altering of the push params.
    $params = new PushParams($mapping, $drupalEntity);
    $this->eventDispatcher()->dispatch(
        new ApiSyncPushParamsEvent($this, $params),
        ApiSyncEvents::PUSH_PARAMS
    );
    $result = match ($this->getPushAction()) {
      PushActions::Update => $this->client()->objectUpdate(
          $this->getApiSyncPath(),
          $params->getParams()
      ),
      PushActions::Create => $this->client()->objectCreate(
          $mapping->getApiSyncObjectType(),
          $params->getParams()
      ),
    };

    if ($drupalEntity instanceof EntityChangedInterface) {
      $this->set('entity_updated', $drupalEntity->getChangedTime());
    }

    if ($result instanceof ODataObjectInterface) {
      $this->setMetaDataOnMappedObject($result);
    }

    // setNewRevision not chainable, per https://www.drupal.org/node/2839075.
    $this->setNewRevision(TRUE);
    $this
      ->set('last_sync_action', 'push_' . $this->getPushAction()->value)
      ->set('last_sync_status', TRUE)
      ->set('revision_log_message', '')
      ->save();

    // Previously hook_apisync_push_success.
    $this->eventDispatcher()->dispatch(
        new ApiSyncPushParamsEvent($this, $params),
        ApiSyncEvents::PUSH_SUCCESS
    );

    return $result;
  }

  /**
   * Get the push action.
   *
   * @return \Drupal\apisync_mapping\PushActions
   *   The push actions.
   */
  public function getPushAction(): PushActions {
    // At present we use the presence API Sync ID to determine if we should
    // create or update the object. In the future we will support setting
    // the API Sync ID client side, which means we will need another way to
    // determine if the object has already been created. In future we will
    // likely rely on an additional field on the bundle class for this.
    return $this->apiSyncId() ? PushActions::Update : PushActions::Create;
  }

  /**
   * {@inheritdoc}
   */
  public function pushDelete(): static {
    $this->client()->objectDelete($this->getApiSyncPath());
    $this->setNewRevision(TRUE);
    $this
      ->set('last_sync_action', 'push_delete')
      ->set('last_sync_status', TRUE)
      ->save();

    // @todo Create custom event type for delete.
    $message = 'Pushed delete successfully to @url.';
    $args = ['@url' => $this->getApiSyncUrl()];
    $eventDispatcher = $this->eventDispatcher();
    $eventDispatcher->dispatch(
        new ApiSyncNoticeEvent(NULL, $message, $args),
        ApiSyncEvents::NOTICE
    );
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setDrupalEntityStub(?FieldableEntityInterface $entity = NULL): static {
    $this->drupalEntityStub = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalEntityStub(): ?FieldableEntityInterface {
    return $this->drupalEntityStub;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiSyncRecord(ODataObjectInterface $oDataRecord): static {
    $this->oDataRecord = $oDataRecord;
    $this->setMetaDataOnMappedObject($this->oDataRecord);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiSyncRecord(): ?ODataObjectInterface {
    return $this->oDataRecord;
  }

  /**
   * Set metadata on mapped object (if not already set).
   *
   * The metadata are defined in field mappings on the mapped object type
   * and must be read only (after being set).
   */
  protected function setMetaDataOnMappedObject(ODataObjectInterface $oDataRecord): void {
    $mapping = $this->getMapping();
    $mappedObjectTypeConfig = $mapping->getRelatedApiSyncMappedObjectType();
    $bundle = $this->bundle();
    if ($mappedObjectTypeConfig->id() !== $bundle) {
      throw new ConfigurationException(
        sprintf(
            'The mapped object must have the same bundle as the mapping ID %s, but has a bundle of %s.',
            $mapping->id(),
            $this->bundle()
        )
      );
    }
    // Get mappings from the foreign entity to the metadata fields stored on
    // the mapped object entity.  We currently trust the type is correct.
    foreach ($mapping->getRelatedApiSyncMappedObjectType()->getFieldMappings() as $fieldMapping) {
      $drupalFieldName = $fieldMapping['drupal_field'];
      $remoteFieldName = $fieldMapping['apisync_field'];
      if (!$this->hasField($drupalFieldName)) {
        throw new ConfigurationException(
          sprintf(
              'The mapped object of type %s, must define the field %s to store remote values from %s.',
              $bundle,
              $drupalFieldName,
              $remoteFieldName
          )
        );
      }
      if ($this->get($drupalFieldName)->isEmpty()) {
        if (!$oDataRecord->hasField($remoteFieldName)) {
          throw new ConfigurationException(
            sprintf(
                'The odata object must define the field %s for mapping %s. This may require a pre pull event subscriber in your code to include the field.',
                $remoteFieldName,
                $mapping->id(),
            )
          );
        }
        // This may include a field mapped directly to the apisync_id, which is
        // only allowed if the apisync_id should not be hashed.
        $this->set($drupalFieldName, $oDataRecord->field($remoteFieldName));
      }
    }

    // For the case where the value is not mapped directly to the apisync_id,
    // such as with a single field whose length exceeds the apisync_id field or
    // where composite keys are needed.
    if ($this->get('apisync_id')->isEmpty()) {
      /** @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apiSyncIdProvider */
      $apiSyncIdProvider = \Drupal::service('apisync_mapping.apisync_id_provider');
      $this->set('apisync_id', $apiSyncIdProvider->getApiSyncId($oDataRecord, $mapping));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function pull(): void {
    $mapping = $this->getMapping();

    // If the pull isn't coming from a cron job.
    if ($this->oDataRecord === NULL) {
      if ($this->apiSyncId()) {
        $path = $this->getApiSyncPath();
        $this->oDataRecord = $this->client()->objectRead(
          $path
        );
      }
      // If the pull is coming from the form, we need to ensure the event for
      // pre pull is dispatched.
      $this->eventDispatcher()->dispatch(
          new ApiSyncPullEvent($this, MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE),
          ApiSyncEvents::PULL_PREPULL
      );
    }

    if ($this->oDataRecord === NULL) {
      throw new NotImplementedException("It is not currently supported to call the pull method without first ensuring the oDataRecord has been set.");
    }

    $fields = $mapping->getPullFields();
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $drupalEntity */
    $drupalEntity = $this->getMappedEntity() ?: $this->getDrupalEntityStub();

    /** @var \Drupal\apisync_mapping\ApiSyncMappingFieldPluginInterface $field */
    foreach ($fields as $field) {
      try {
        $value = $field->pullValue($this->oDataRecord, $drupalEntity, $mapping);
      }
      catch (\Exception $e) {
        // Field missing from OData record? Skip it.
        $message = 'Field @obj.@field not found on @id';
        $args = [
          '@obj' => $mapping->getApiSyncObjectType(),
          '@field' => $field->config('apisync_field'),
          '@id' => $this->apiSyncId(),
        ];
        $this->eventDispatcher()
          ->dispatch(new ApiSyncNoticeEvent($e, $message, $args), ApiSyncEvents::NOTICE);
        continue;
      }

      $this->eventDispatcher()->dispatch(
          new ApiSyncPullEntityValueEvent($value, $field, $this),
          ApiSyncEvents::PULL_ENTITY_VALUE
      );
      try {
        // If $value is TypedData, it should have been set during pullValue().
        if (!$value instanceof TypedDataInterface) {
          $drupalField = $field->get('drupal_field_value');
          // Support pulling with RelatedProperties field.
          if (strpos($drupalField, ':')) {
            [$drupalField, $subFieldName] = explode(':', $drupalField, 2);

            /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field */
            $field = $drupalEntity->get($drupalField);
            $referencedEntities = $field->referencedEntities();

            if (!empty($referencedEntities)) {
              /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
              $entity = reset($referencedEntities);
              $entity->set($subFieldName, $value);
              $entity->save();
            }
            continue;
          }
          $drupalEntity->set($drupalField, $value);
        }
      }
      catch (\Exception $e) {
        $message = 'Exception during pull for @obj.@field @id to @dobj.@dprop @did with value @v';
        $args = [
          '@obj' => $mapping->getApiSyncObjectType(),
          '@field' => $field->config('apisync_field'),
          '@id' => $this->apiSyncId(),
          '@dobj' => $drupalEntity->getEntityTypeId(),
          '@dprop' => $field->get('drupal_field_value'),
          '@did' => $drupalEntity->id(),
          '@v' => $value,
        ];
        $this->eventDispatcher()->dispatch(new ApiSyncWarningEvent($e, $message, $args), ApiSyncEvents::WARNING);
        continue;
      }
    }

    $this->eventDispatcher()->dispatch(
        new ApiSyncPullEvent(
            $this,
            $drupalEntity->isNew()
              ? MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE
              : MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE
        ),
        ApiSyncEvents::PULL_PRESAVE
    );

    // Set a flag here to indicate that a pull is happening, to avoid
    // triggering a push.
    $drupalEntity->apisync_pull = TRUE;
    $drupalEntity->save();

    $this->setMetaDataOnMappedObject($this->oDataRecord);
    $this
      ->set('drupal_entity', $drupalEntity)
      ->set('entity_updated', $this->getRequestTime())
      ->set('last_sync_action', 'pull')
      ->set('last_sync_status', TRUE)
      ->set('force_pull', 0)
      ->save();
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

}
