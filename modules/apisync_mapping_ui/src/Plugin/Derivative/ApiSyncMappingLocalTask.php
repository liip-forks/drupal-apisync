<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class ApiSyncMappingLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates an ApiSyncMappingLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The translation manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TranslationInterface $stringTranslation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId): static {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if (!($entityType->hasLinkTemplate('apisync'))) {
        continue;
      }
      $this->derivatives["$entityTypeId.apisync_tab"] = [
        'route_name' => "entity.$entityTypeId.apisync",
        'title' => $this->t('API Sync'),
        'base_route' => "entity.$entityTypeId.canonical",
        'weight' => 200,
      ] + $basePluginDefinition;
      $this->derivatives["$entityTypeId.apisync"] = [
        'route_name' => "entity.$entityTypeId.apisync",
        'weight' => 200,
        'title' => $this->t('View'),
        'parent_id' => "apisync_mapping.entities:$entityTypeId.apisync_tab",
      ] + $basePluginDefinition;

      // Show a tab on the profile edit form as well.
      if ($entityTypeId === 'profile') {
        $this->derivatives["entity.$entityTypeId.edit_form"] = [
          'route_name' => "entity.$entityTypeId.edit_form",
          'title' => $this->t('Edit Form'),
          'base_route' => "entity.$entityTypeId.edit_form",
          'weight' => 10,
        ] + $basePluginDefinition;

        $this->derivatives["$entityTypeId.apisync_tab"] = [
          'route_name' => "entity.$entityTypeId.apisync",
          'title' => $this->t('API Sync'),
          'base_route' => "entity.$entityTypeId.edit_form",
          'weight' => 200,
        ] + $basePluginDefinition;
      }

    }

    return $this->derivatives;
  }

}
