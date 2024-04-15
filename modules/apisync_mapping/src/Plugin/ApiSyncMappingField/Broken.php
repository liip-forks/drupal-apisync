<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "broken",
 *   label = @Translation("Broken")
 * )
 */
class Broken extends ApiSyncMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    // Try to preserve existing, broken config, so that it works again when the
    // plugin gets restored:
    $pluginForm = parent::buildConfigurationForm($form, $formState);
    return $this->buildBrokenConfigurationForm($pluginForm, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): mixed {

  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(ApiSyncMappingInterface $mapping): bool {
    return FALSE;
  }

}
