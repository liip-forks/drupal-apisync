<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Plugin\ApiSyncMappingField;

use Drupal\apisync_mapping\ApiSyncMappingFieldPluginBase;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "Constant",
 *   label = @Translation("Constant")
 * )
 */
class Constant extends ApiSyncMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $pluginForm = parent::buildConfigurationForm($form, $formState);

    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a constant value to map to a API Sync field.'),
    ];

    $pluginForm['direction']['#options'] = [
      MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE => $pluginForm['direction']['#options'][MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE],
    ];
    $pluginForm['direction']['#default_value'] =
      MappingConstants::APISYNC_MAPPING_DIRECTION_DRUPAL_REMOTE;

    return $pluginForm;

  }

  /**
   * {@inheritdoc}
   *
   * @return array|string|null
   *   The value to be pushed to remote.
   */
  public function value(EntityInterface $entity, ApiSyncMappingInterface $mapping): array|string|null {
    return $this->config('drupal_field_value');
  }

  /**
   * {@inheritdoc}
   */
  public function pull(): bool {
    return FALSE;
  }

}
