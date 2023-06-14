<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * API Sync Mapping Add/Edit Form.
 */
class ApiSyncMappingAddForm extends ApiSyncMappingFormCrudBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    parent::save($form, $formState);

    $formState->setRedirect('entity.apisync_mapped_object_type.add_form', ['mapping_id' => $this->entity->id()]);
  }

}
