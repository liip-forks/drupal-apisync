<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\apisync\Event\ApiSyncErrorEvent;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * API Sync Mapping Form base.
 */
abstract class ApiSyncMappingFormCrudBase extends ApiSyncMappingFormBase {

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected DateFormatter $dateFormatter;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructor for a ApiSyncMappingCrudBase object.
   *
   * @param \Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager $mappingFieldPluginManager
   *   Mapping plugin manager.
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   Rest client.
   * @param \Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface $mappableEntityTypes
   *   Mappable types.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info service.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   */
  public function __construct(
      ApiSyncMappingFieldPluginManager $mappingFieldPluginManager,
      ODataClientInterface $client,
      ApiSyncMappableEntityTypesInterface $mappableEntityTypes,
      EntityTypeBundleInfoInterface $bundleInfo,
      DateFormatter $dateFormatter,
      StateInterface $state,
      EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct($mappingFieldPluginManager, $client, $mappableEntityTypes, $bundleInfo);
    $this->dateFormatter = $dateFormatter;
    $this->state = $state;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('plugin.manager.apisync_mapping_field'),
        $container->get('apisync.odata_client'),
        $container->get('apisync_mapping.mappable_entity_types'),
        $container->get('entity_type.bundle.info'),
        $container->get('date.formatter'),
        $container->get('state'),
        $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->ensureConnection()) {
      return $form;
    }

    $form = parent::buildForm($form, $form_state);
    $mapping = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $mapping->label(),
      '#required' => TRUE,
      '#weight' => -30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#required' => TRUE,
      '#default_value' => $mapping->id(),
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#machine_name' => [
        'exists' => ['Drupal\apisync_mapping\Entity\ApiSyncMapping', 'load'],
        'source' => ['label'],
      ],
      '#disabled' => !$mapping->isNew(),
      '#weight' => -20,
    ];

    $form['drupal_entity'] = [
      '#title' => $this->t('Drupal entity'),
      '#type' => 'details',
      '#attributes' => [
        'id' => 'edit-drupal-entity',
      ],
      // Gently discourage admins from breaking existing fieldmaps:
      '#open' => $mapping->isNew(),
    ];

    $entityTypes = $this->getEntityTypeOptions();
    $form['drupal_entity']['drupal_entity_type'] = [
      '#title' => $this->t('Drupal Entity Type'),
      '#id' => 'edit-drupal-entity-type',
      '#type' => 'select',
      '#description' => $this->t('Select a Drupal entity type to map to a API Sync object.'),
      '#options' => $entityTypes,
      '#default_value' => $mapping->isNew() ? NULL : $mapping->drupal_entity_type,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'callback' => [$this, 'bundleCallback'],
        'event' => 'change',
        'wrapper' => 'drupal_bundle',
      ],
    ];

    $form['drupal_entity']['drupal_bundle'] = [
      '#title' => $this->t('Bundle'),
      '#type' => 'select',
      '#default_value' => $mapping->isNew() ? NULL : $mapping->drupal_bundle,
      '#empty_option' => $this->t('- Select -'),
      // Bundle select options will get completely replaced after user selects
      // an entity, but we include all possibilities here for js-free
      // compatibility (for simpletest)
      '#options' => $this->getBundleOptions(),
      '#required' => TRUE,
      '#prefix' => '<div id="drupal_bundle">',
      '#suffix' => '</div>',
      // Don't expose the bundle listing until user has selected an entity.
      '#states' => [
        'visible' => [
          ':input[name="drupal_entity_type"]' => ['!value' => ''],
        ],
      ],
    ];
    $input = $form_state->getUserInput();
    if (!empty($input) && !empty($input['drupal_entity_type'])) {
      $entityType = $input['drupal_entity_type'];
    }
    else {
      $entityType = $form['drupal_entity']['drupal_entity_type']['#default_value'];
    }
    $bundleInfo = $this->bundleInfo->getBundleInfo($entityType);

    if (!empty($bundleInfo)) {
      $form['drupal_entity']['drupal_bundle']['#options'] = [];
      $form['drupal_entity']['drupal_bundle']['#title'] = $this->t(
          '@entity_type Bundle',
          ['@entity_type' => $entityTypes[$entityType]]
      );
      foreach ($bundleInfo as $key => $info) {
        $form['drupal_entity']['drupal_bundle']['#options'][$key] = $info['label'];
      }
    }

    $form['apisync_object'] = [
      '#title' => $this->t('OData object'),
      '#id' => 'edit-apisync-object',
      '#type' => 'details',
      // Gently discourage admins from breaking existing fieldmaps:
      '#open' => $mapping->isNew(),
    ];

    $apisyncObjectType = '';
    if (!empty($form_state->getValues()) && !empty($form_state->getValue('apisync_object_type'))) {
      $apisyncObjectType = $form_state->getValue('apisync_object_type');
    }
    elseif (!$mapping->isNew() && $mapping->apisync_object_type) {
      $apisyncObjectType = $mapping->apisync_object_type;
    }
    $form['apisync_object']['apisync_object_type'] = [
      '#title' => $this->t('OData Object'),
      '#id' => 'edit-apisync-object-type',
      '#type' => 'select',
      '#description' => $this->t('Select a OData object to map.'),
      '#default_value' => $apisyncObjectType,
      '#options' => $this->getApiSyncObjectTypeOptions(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $triggerOptions = $this->getSyncTriggerOptions();
    $form['sync_triggers'] = [
      '#title' => $this->t('Action triggers'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t('Select which actions on Drupal entities and API Sync
        objects should trigger a synchronization. These settings are used by the
        apisync_push and apisync_pull modules.'
      ),
    ];
    if (empty($triggerOptions)) {
      $form['sync_triggers']['#description'] .= ' ' . $this->t('<br/><em>No trigger options are available when API sync Push and Pull modules are disabled. Enable one or both modules to allow Push or Pull processing.</em>');
    }

    foreach ($triggerOptions as $option => $label) {
      $form['sync_triggers'][$option] = [
        '#title' => $label,
        '#type' => 'checkbox',
        '#default_value' => !empty($mapping->sync_triggers[$option]),
      ];
    }

    if ($this->moduleHandler->moduleExists('apisync_pull')) {
      $form['pull'] = [
        '#title' => $this->t('Pull Settings'),
        '#type' => 'details',
        '#description' => '',
        '#open' => TRUE,
        '#tree' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name^="sync_triggers[pull"]' => ['checked' => TRUE],
          ],
        ],
      ];

      if (!$mapping->isNew()) {
        $form['pull']['last_pull_date'] = [
          '#type' => 'item',
          '#title' => $this->t(
              'Last Pull Date: %last_pull',
              [
                '%last_pull' => $mapping->getLastPullTime()
                ? $this->dateFormatter->format($mapping->getLastPullTime())
                : 'never',
              ]
          ),
          '#markup' => $this->t('Resetting last pull date will cause API sync pull module to query for updated records without respect for the pull trigger date. This is useful, for example, to re-pull all records after a purge.'),
        ];
        $form['pull']['last_pull_reset'] = [
          '#type' => 'button',
          '#value' => $this->t('Reset Last Pull Date'),
          '#disabled' => $mapping->getLastPullTime() == NULL,
          '#limit_validation_errors' => [],
          '#validate' => ['::lastPullReset'],
        ];

        $form['pull']['last_delete_date'] = [
          '#type' => 'item',
          '#title' => $this->t(
              'Last Delete Date: %last_pull',
              [
                '%last_pull' => $mapping->getLastDeleteTime()
                ? $this->dateFormatter->format($mapping->getLastDeleteTime())
                : 'never',
              ]
          ),
          '#markup' => $this->t('Resetting last delete date will cause the API sync pull module to query for deleted record without respect for the pull trigger date.'),
        ];
        $form['pull']['last_delete_reset'] = [
          '#type' => 'button',
          '#value' => $this->t('Reset Last Delete Date'),
          '#disabled' => $mapping->getLastDeleteTime() == NULL,
          '#limit_validation_errors' => [],
          '#validate' => ['::lastDeleteReset'],
        ];

        // This doesn't work until after mapping gets saved.
        // @todo Figure out best way to alert admins about this, or AJAX-ify it.
        // This means that you first need to create the mapping without any
        // actions selected.
        $form['pull']['pull_trigger_date'] = [
          '#type' => 'select',
          '#title' => $this->t('Date field to trigger pull'),
          '#description' => $this->t('Poll the remote endpoint for updated records based on the given date field. Defaults to "Last Modified Date".'),
          '#required' => FALSE,
          '#default_value' => $mapping->pull_trigger_date,
          '#options' => $this->getPullTriggerOptions(),
        ];
      }

      $form['pull']['pull_where_clause'] = [
        '#title' => $this->t('Pull query "Where" clause'),
        '#type' => 'textarea',
        '#description' => $this->t('Add a "where" condition clause to limit records pulled from the remote endpoint. e.g. Email != \'\' AND UID = ExampleUID'),
        '#default_value' => $mapping->pull_where_clause,
      ];

      $form['pull']['pull_where_clause'] = [
        '#title' => $this->t('Pull query "Where" clause'),
        '#type' => 'textarea',
        '#description' => $this->t('Add a "where" condition clause to limit records pulled from the remote endpoint. e.g. Email != \'\' AND UID = ExampleUID'),
        '#default_value' => $mapping->pull_where_clause,
      ];

      $form['pull']['pull_frequency'] = [
        '#title' => $this->t('Pull Frequency'),
        '#type' => 'number',
        '#default_value' => $mapping->pull_frequency,
        '#description' => $this->t('Enter a frequency, in seconds, for how often this mapping should be used to pull data to Drupal. Enter 0 to pull as often as possible. FYI: 1 hour = 3600; 1 day = 86400. <em>NOTE: pull frequency is shared per-API Sync Object. The setting is exposed here for convenience.</em>'),
      ];

      $description = $this->t('Check this box to disable cron pull processing for this mapping, and allow standalone processing only. A URL will be generated after saving the mapping.');
      if ($mapping->id()) {
        $standaloneUrl = Url::fromRoute(
            'apisync_pull.endpoint.apisync_mapping',
            [
              'apisync_mapping' => $mapping->id(),
              'key' => $this->state->get('system.cron_key'),
            ],
            ['absolute' => TRUE]
        )->toString();
        $description = $this->t(
            'Check this box to disable cron pull processing for this mapping, and allow standalone processing via this URL: <a href=":url">:url</a>',
            [':url' => $standaloneUrl]
        );
      }
      $form['pull']['pull_standalone'] = [
        '#title' => $this->t('Enable standalone pull queue processing'),
        '#type' => 'checkbox',
        '#description' => $description,
        '#default_value' => $mapping->pull_standalone,
      ];

      // If global standalone is enabled, then we force this mapping's
      // standalone property to true.
      if ($this->config('apisync.settings')->get('standalone')) {
        $settingsUrl = Url::fromRoute('apisync.global_settings')->toString();
        $form['pull']['pull_standalone']['#default_value'] = TRUE;
        $form['pull']['pull_standalone']['#disabled'] = TRUE;
        $form['pull']['pull_standalone']['#description'] .= ' ' . $this->t(
            'See also <a href="@url">global standalone processing settings</a>.',
            ['@url' => $settingsUrl]
        );
      }
    }

    if ($this->moduleHandler->moduleExists('apisync_push')) {
      $form['push'] = [
        '#title' => $this->t('Push Settings'),
        '#type' => 'details',
        '#description' => $this->t('The asynchronous push queue is always enabled in Drupal 8: real-time push fails are queued for async push. Alternatively, you can choose to disable real-time push and use async-only.'),
        '#open' => TRUE,
        '#tree' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name^="sync_triggers[push"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['push']['async'] = [
        '#title' => $this->t('Disable real-time push'),
        '#type' => 'checkbox',
        '#description' => $this->t('When real-time push is disabled, enqueue changes and push to remote asynchronously during cron. When disabled, push changes immediately upon entity CRUD, and only enqueue failures for async push.'),
        '#default_value' => $mapping->async,
      ];

      $form['push']['push_frequency'] = [
        '#title' => $this->t('Push Frequency'),
        '#type' => 'number',
        '#default_value' => $mapping->push_frequency,
        '#description' => $this->t('Enter a frequency, in seconds, for how often this mapping should be used to push data to remote. Enter 0 to push as often as possible. FYI: 1 hour = 3600; 1 day = 86400.'),
        '#min' => 0,
      ];

      $form['push']['push_limit'] = [
        '#title' => $this->t('Push Limit'),
        '#type' => 'number',
        '#default_value' => $mapping->push_limit,
        '#description' => $this->t('Enter the maximum number of records to be pushed to remote during a single queue batch. Enter 0 to process as many records as possible, subject to the global push queue limit.'),
        '#min' => 0,
      ];

      $form['push']['push_retries'] = [
        '#title' => $this->t('Push Retries'),
        '#type' => 'number',
        '#default_value' => $mapping->push_retries,
        '#description' => $this->t("Enter the maximum number of attempts to push a record to remote before it's considered failed. Enter 0 for no limit."),
        '#min' => 0,
      ];

      $form['push']['weight'] = [
        '#title' => $this->t('Weight'),
        '#type' => 'select',
        '#options' => array_combine(range(-50, 50), range(-50, 50)),
        '#description' => $this->t('Not yet in use. During cron, mapping weight determines in which order items will be pushed. Lesser weight items will be pushed before greater weight items.'),
        '#default_value' => $mapping->weight,
      ];

      $description = $this->t('Check this box to disable cron push processing for this mapping, and allow standalone processing. A URL will be generated after saving the mapping.');
      if ($mapping->id()) {
        $standaloneUrl = Url::fromRoute(
            'apisync_push.endpoint.apisync_mapping',
            [
              'apisync_mapping' => $mapping->id(),
              'key' => $this->state->get('system.cron_key'),
            ],
            ['absolute' => TRUE]
        )->toString();
        $description = $this->t(
            'Check this box to disable cron push processing for this mapping, and allow standalone processing via this URL: <a href=":url">:url</a>',
            [':url' => $standaloneUrl]
        );
      }

      $form['push']['push_standalone'] = [
        '#title' => $this->t('Enable standalone push queue processing'),
        '#type' => 'checkbox',
        '#description' => $description,
        '#default_value' => $mapping->push_standalone,
      ];

      // If global standalone is enabled, then we force this mapping's
      // standalone property to true.
      if ($this->config('apisync.settings')->get('standalone')) {
        $settingsUrl = Url::fromRoute('apisync.global_settings')->toString();
        $form['push']['push_standalone']['#default_value'] = TRUE;
        $form['push']['push_standalone']['#disabled'] = TRUE;
        $form['push']['push_standalone']['#description'] .= ' ' . $this->t(
            'See also <a href="@url">global standalone processing settings</a>.',
            ['@url' => $settingsUrl]
        );
      }
    }

    $form['meta'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
      '#title' => $this->t('Additional properties'),
    ];

    $form['meta']['weight'] = [
      '#title' => $this->t('Weight'),
      '#type' => 'select',
      '#options' => array_combine(range(-50, 50), range(-50, 50)),
      '#description' => $this->t('During cron, mapping weight determines in which order items will be pushed or pulled. Lesser weight items will be pushed or pulled before greater weight items.'),
      '#default_value' => $mapping->weight,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    $bundles = $this->bundleInfo->getBundleInfo($formState->getValue('drupal_entity_type'));
    if (empty($bundles[$formState->getValue('drupal_bundle')])) {
      $formState->setErrorByName('drupal_bundle', $this->t('Invalid bundle for entity type.'));
    }
    $button = $formState->getTriggeringElement();
    if ($button['#id'] != $form['actions']['submit']['#id']) {
      // Skip validation unless we hit the "save" button.
      return;
    }

    parent::validateForm($form, $formState);

    if (!$this->entity->isNew() && $this->entity->doesPull()) {
      try {
        // As this is just a test pull we don't need to allow params to be
        // modified.
        $this->client->query($this->entity->getPullQuery());
      }
      catch (\Exception $e) {
        $formState->setError(
            $form['pull']['pull_where_clause'],
            $this->t('Test pull query returned an error. Please check logs for error details.')
        );
        $this->eventDispatcher->dispatch(new ApiSyncErrorEvent($e), ApiSyncEvents::ERROR);
      }
    }
  }

  /**
   * Submit handler for "reset pull timestamp" button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function lastPullReset(array $form, FormStateInterface $formState): void {
    $mapping = $this->entity->setLastPullTime(NULL);
    /** @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface $mappedObjectStorage */
    $mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
    $mappedObjectStorage->setForcePull($mapping);
  }

  /**
   * Submit handler for "reset delete timestamp" button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   */
  public function lastDeleteReset(array $form, FormStateInterface $formState): void {
    $this->entity->setLastDeleteTime(NULL);
  }

  /**
   * Ajax callback for apisync_mapping_form() bundle selection.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return mixed
   *   Drupal bundle.
   */
  public function bundleCallback(array $form, FormStateInterface $formState): mixed {
    return $form['drupal_entity']['drupal_bundle'];
  }

  /**
   * Return an array of all bundle options, for javascript-free fallback.
   *
   * @return array
   *   Options.
   */
  protected function getBundleOptions(): array {
    $entities = $this->getEntityTypeOptions();
    $bundles = $this->bundleInfo->getAllBundleInfo();
    $options = [];
    foreach ($bundles as $entity => $bundleInfo) {
      if (empty($entities[$entity])) {
        continue;
      }
      foreach ($bundleInfo as $bundle => $info) {
        $entityLabel = $entities[$entity];
        $options[(string) $entityLabel][$bundle] = (string) $info['label'];
      }
    }
    return $options;
  }

  /**
   * Return a list of Drupal entity types for mapping.
   *
   * @return array
   *   An array of values keyed by machine name of the entity with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getEntityTypeOptions(): array {
    $options = [];
    $mappableEntityTypes = $this->mappableEntityTypes->getMappableEntityTypes();
    foreach ($mappableEntityTypes as $info) {
      $options[$info->id()] = $info->getLabel();
    }
    uasort($options, function ($a, $b) {
      return strcmp($a->render(), $b->render());
    });
    return $options;
  }

  /**
   * Helper to retreive a list of object type options.
   *
   * @return array
   *   An array of values keyed by machine name of the object with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getApiSyncObjectTypeOptions(): array {
    $apisyncObjectOptions = [];

    $apisyncObjects = $this->client->objects();
    foreach ($apisyncObjects as $object) {
      $apisyncObjectOptions[$object['name']] = $object['label'] . ' (' . $object['name'] . ')';
    }
    asort($apisyncObjectOptions);
    return $apisyncObjectOptions;
  }

  /**
   * Return form options for available sync triggers.
   *
   * @return array
   *   Array of sync trigger options keyed by their machine name with their
   *   label as the value.
   */
  protected function getSyncTriggerOptions(): array {
    $options = [];
    if ($this->moduleHandler->moduleExists('apisync_push')) {
      $options += [
        MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_CREATE => $this->t('Drupal entity create (push)'),
        MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_UPDATE => $this->t('Drupal entity update (push)'),
        MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_DELETE => $this->t('Drupal entity delete (push)'),
      ];
    }
    if ($this->moduleHandler->moduleExists('apisync_pull')) {
      $options += [
        MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_CREATE => $this->t('API object create (pull)'),
        MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_UPDATE => $this->t('API object update (pull)'),
        MappingConstants::APISYNC_MAPPING_SYNC_REMOTE_DELETE => $this->t('API object delete (pull)'),
      ];
    }
    return $options;
  }

  /**
   * Return an array of Date fields suitable for use a pull trigger field.
   *
   * @return array
   *   The options array.
   */
  private function getPullTriggerOptions(): array {
    $options = [];
    try {
      $describe = $this->getApiSyncObject();
    }
    catch (\Exception $e) {
      // No describe results means no datetime fields. We're done.
      return [];
    }

    foreach ($describe['fields'] as $field) {
      if ($field['Type'] == 'Edm.DateTimeOffset') {
        $options[$field['Name']] = $field['Name'];
      }
    }
    return $options;
  }

}
