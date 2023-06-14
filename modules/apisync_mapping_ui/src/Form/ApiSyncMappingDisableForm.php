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
class ApiSyncMappingDisableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to disable the mapping %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Disabling a mapping will stop any automatic synchronization and hide the mapping.');
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
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMapping $entity */
    $entity = $this->entity;
    $entity->disable()->save();
    $formState['redirect_route'] = [
      'route_name' => 'entity.apisync_mapping.list',
    ];
  }

}
