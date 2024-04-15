<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * API Sync Mapping Delete Form.
 */
class ApiSyncMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the mapping %name?', ['%name' => $this->entity->label()]);
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
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $this->entity->delete();

    // Set a message that the entity was deleted.
    $this->messenger()->addStatus($this->t('API Sync %label was deleted.', [
      '%label' => $this->entity->label(),
    ]));

    $formState->setRedirectUrl($this->getCancelUrl());
  }

}
