<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token as TokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter for entity Token and fields.
 *
 * @Plugin(
 *   id = "Token",
 *   label = @Translation("Token"),
 *   provider = "token"
 * )
 */
class Token extends ApiSyncMappingFieldPluginBase {

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected TokenService $token;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Constructor for a Token object.
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
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
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
      TokenService $token,
      RendererInterface $renderer
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
    $this->token = $token;
    $this->renderer = $renderer;
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
        $container->get('token'),
        $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);

    $tokenBrowser = [
      'token_browser' => [
        '#theme' => 'token_tree_link',
        '#token_types' => [$form['#entity']->getDrupalEntityType()],
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
      ],
    ];

    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a token to map a API Sync field. @token_browser', [
        '@token_browser' => $this->renderer->renderRoot($tokenBrowser),
      ]),
    ];

    $pluginForm['direction']['#options'] = [
      MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE => $pluginForm['direction']['#options'][MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE],
    ];
    $pluginForm['direction']['#default_value'] = MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE;

    return $pluginForm;
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed {
    $text = $this->config('drupal_field_value');
    $data = [$entity->getEntityTypeId() => $entity];
    $options = ['clear' => TRUE];
    $result = $this->token->replace($text, $data, $options);
    // If we have something, return it. Otherwise return NULL.
    return (trim($result) != '') ? $result : NULL;
  }

  /**
   * Pull-token doesn't make sense. This is a no-op.
   */
  public function pull(): bool {
    return FALSE;
  }

}
