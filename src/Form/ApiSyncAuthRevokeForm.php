<?php

declare(strict_types = 1);

namespace Drupal\apisync\Form;

use Drupal\apisync\Entity\ApiSyncAuthConfigInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * API Sync Auth Revoke Form.
 */
class ApiSyncAuthRevokeForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t(
        'Are you sure you want to revoke authorization for Auth Config %name?',
        ['%name' => $this->entity->label()]
    );
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
    return $this->t('Revoke Auth Token');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    if (!$this->entity instanceof ApiSyncAuthConfigInterface) {
      return;
    }
    $this->entity->getPlugin()->revokeAccessToken();

    // Set a message that the entity was deleted.
    $this->messenger()->addStatus($this->t('Auth token for %label was revoked.', [
      '%label' => $this->entity->label(),
    ]));

    $formState->setRedirectUrl($this->getCancelUrl());
  }

}
