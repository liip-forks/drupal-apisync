<?php

declare(strict_types=1);

namespace Drupal\apisync\Form;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates authorization form for API Sync.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The sevent dispatcher service..
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected ContainerAwareEventDispatcher $eventDispatcher;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * State storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage service.
   */
  public function __construct(
      ConfigFactoryInterface $configFactory,
      ContainerAwareEventDispatcher $eventDispatcher,
      TypedConfigManagerInterface $typedConfigManager,
      ModuleHandlerInterface $moduleHandler,
      StateInterface $state
  ) {
    parent::__construct($configFactory);
    $this->eventDispatcher = $eventDispatcher;
    $this->typedConfigManager = $typedConfigManager;
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('config.factory'),
        $container->get('event_dispatcher'),
        $container->get('config.typed'),
        $container->get('module_handler'),
        $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'apisync_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'apisync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // We're not actually doing anything with this, but may figure out
    // something that makes sense.
    $config = $this->config('apisync.settings');
    $definition = $this->typedConfigManager->getDefinition('apisync.settings');
    $definition = $definition['mapping'];

    // phpcs:disable Drupal.Semantics.FunctionT

    $form['short_term_cache_lifetime'] = [
      '#title' => $this->t($definition['short_term_cache_lifetime']['label']),
      '#description' => $this->t($definition['short_term_cache_lifetime']['description']),
      '#type' => 'number',
      '#default_value' => $config->get('short_term_cache_lifetime'),
    ];

    $form['long_term_cache_lifetime'] = [
      '#title' => $this->t($definition['long_term_cache_lifetime']['label']),
      '#description' => $this->t($definition['long_term_cache_lifetime']['description']),
      '#type' => 'number',
      '#default_value' => $config->get('short_term_cache_lifetime'),
    ];

    if ($this->moduleHandler->moduleExists('apisync_push')) {
      $form['global_push_limit'] = [
        '#title' => $this->t($definition['global_push_limit']['label']),
        '#type' => 'number',
        '#description' => $this->t($definition['global_push_limit']['description']),
        '#required' => TRUE,
        '#default_value' => $config->get('global_push_limit'),
        '#min' => 0,
      ];
    }

    if ($this->moduleHandler->moduleExists('apisync_pull')) {
      $form['pull_max_queue_size'] = [
        '#title' => $this->t($definition['pull_max_queue_size']['label']),
        '#type' => 'number',
        '#description' => $this->t($definition['pull_max_queue_size']['description']),
        '#required' => TRUE,
        '#default_value' => $config->get('pull_max_queue_size'),
        '#min' => 0,
      ];
    }

    if ($this->moduleHandler->moduleExists('apisync_mapping')) {
      $form['limit_mapped_object_revisions'] = [
        '#title' => $this->t($definition['limit_mapped_object_revisions']['label']),
        '#description' => $this->t($definition['limit_mapped_object_revisions']['description']),
        '#type' => 'number',
        '#required' => TRUE,
        '#default_value' => $config->get('limit_mapped_object_revisions'),
        '#min' => 0,
      ];
    }

    $form['allowlist_entity_types'] = [
      '#title' => $this->t($definition['allowlist_entity_types']['label']),
      '#description' => $this->t($definition['allowlist_entity_types']['description']),
      '#type' => 'textarea',
      '#default_value' => $config->get('allowlist_entity_types'),
    ];
    $form['allowlist_entity_sets'] = [
      '#title' => $this->t($definition['allowlist_entity_sets']['label']),
      '#description' => $this->t($definition['allowlist_entity_sets']['description']),
      '#type' => 'textarea',
      '#default_value' => $config->get('allowlist_entity_sets'),
    ];

    if ($this->moduleHandler->moduleExists('apisync_push') || $this->moduleHandler->moduleExists('apisync_pull')) {
      $form['standalone'] = [
        '#title' => $this->t($definition['standalone']['label']),
        '#description' => $this->t($definition['standalone']['description']),
        '#type' => 'checkbox',
        '#default_value' => $config->get('standalone'),
      ];

      if ($this->moduleHandler->moduleExists('apisync_push')) {
        $standalonePushUrl = Url::fromRoute(
            'apisync_push.endpoint',
            ['key' => $this->state->get('system.cron_key')],
            ['absolute' => TRUE]
        );
        $form['standalone_push_url'] = [
          '#type' => 'item',
          '#title' => $this->t('Standalone Push URL'),
          '#markup' => $this->t('<a href="@url">@url</a>', ['@url' => $standalonePushUrl->toString()]),
          '#states' => [
            'visible' => [
              ':input#edit-standalone' => ['checked' => TRUE],
            ],
          ],
        ];
      }
      if ($this->moduleHandler->moduleExists('apisync_pull')) {
        $standalonePullUrl = Url::fromRoute(
            'apisync_pull.endpoint',
            ['key' => $this->state->get('system.cron_key')],
            ['absolute' => TRUE]
        );
        $form['standalone_pull_url'] = [
          '#type' => 'item',
          '#title' => $this->t('Standalone Pull URL'),
          '#markup' => $this->t('<a href="@url">@url</a>', ['@url' => $standalonePullUrl->toString()]),
          '#states' => [
            'visible' => [
              ':input#edit-standalone' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    // phpcs:enable

    $form = parent::buildForm($form, $form_state);
    $form['creds']['actions'] = $form['actions'];
    unset($form['actions']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $config = $this->config('apisync.settings');
    $config->set('show_all_objects', $formState->getValue('show_all_objects'));
    $config->set('standalone', $formState->getValue('standalone'));
    $config->set('global_push_limit', $formState->getValue('global_push_limit'));
    $config->set('pull_max_queue_size', $formState->getValue('pull_max_queue_size'));
    $config->set('limit_mapped_object_revisions', $formState->getValue('limit_mapped_object_revisions'));

    $config->set('allowlist_entity_types', $formState->getValue('allowlist_entity_types'));
    $config->set('allowlist_entity_sets', $formState->getValue('allowlist_entity_sets'));

    $useLatest = $formState->getValue('use_latest');
    $config->set('use_latest', $useLatest);
    $config->save();
    parent::submitForm($form, $formState);
  }

}
