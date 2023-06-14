<?php

declare(strict_types = 1);

namespace Drupal\apisync\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * API Sync Auth Delete Form.
 */
class ApiSyncAuthDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the Auth Config %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->entity->toUrl('collection');
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
  public function validateForm(array &$form, FormStateInterface $formState): void {
    parent::validateForm($form, $formState);
    if ($formState->getErrors()) {
      return;
    }
    if (\Drupal::config('apisync.settings')->get('apisync_auth_provider') == $this->entity->id()) {
      $formState->setError(
          $form,
          $this->t(
              'You cannot delete the default auth provider. Please <a href="@href">assign a new auth provider</a> before deleting the active one.',
              ['@href' => Url::fromRoute('apisync.auth_config')->toString()]
          )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $this->entity->delete();

    // Set a message that the entity was deleted.
    $this->messenger()->addStatus($this->t('Auth Config %label was deleted.', [
      '%label' => $this->entity->label(),
    ]));

    $formState->setRedirectUrl($this->getCancelUrl());
  }

}
