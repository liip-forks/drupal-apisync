<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * API Sync Mapping Disable Form .
 */
class ApiSyncMappingEnableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to enable the mapping %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Enabling a mapping will restart any automatic synchronization.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.apisync_mapping.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMapping $entity */
    $entity = $this->entity;
    $entity->enable()->save();
    $formState['redirect_route'] = [
      'route_name' => 'entity.apisync_mapping.edit_form',
      'route_parameters' => ['apisync_mapping' => $entity->id()],
    ];
  }

}
