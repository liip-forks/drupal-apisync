<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the filter format list controller.
 */
class ApiSyncMappingList extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'apisync_mapping_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['drupal_entity_type'] = $this->t('Drupal Entity');
    $header['drupal_bundle'] = $this->t('Drupal Bundle');
    $header['apisync_object_type'] = $this->t('API Sync Object');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $entity */
    $row = [];
    $row['label'] = $entity->label();
    $properties = [
      'drupal_entity_type',
      'drupal_bundle',
      'apisync_object_type',
    ];
    foreach ($properties as $property) {
      $row[$property] = ['#markup' => $entity->get($property)];
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Save changes');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    $url = Url::fromRoute('entity.apisync_mapping.fields', ['apisync_mapping' => $entity->id()]);

    // Only makes sense to expose fields operation if edit exists.
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Settings');
      $operations['fields'] = [
        'title' => $this->t('Fields'),
        'url' => $url,
        'weight' => -1000,
      ];
    }

    return $operations;
  }

}
