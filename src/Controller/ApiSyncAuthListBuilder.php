<?php

declare(strict_types=1);

namespace Drupal\apisync\Controller;

use Drupal\apisync\Entity\ApiSyncAuthConfigInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for apisync_auth.
 */
class ApiSyncAuthListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\apisync\Entity\ApiSyncAuthConfigInterface $entity
   *   The entity for this row of the list.
   *
   * @return array
   *   A render array structure of fields for this entity.
   */
  public function buildRow(EntityInterface $entity): array { // phpcs:ignore
    $plugin = $entity->getPlugin();
    $row['default'] = $entity->authManager()
      ->getConfig() && $entity->authManager()
      ->getConfig()
      ->id() == $entity->id()
      ? $this->t('Default') : '';
    $row['label'] = $entity->label();
    $row['url'] = $plugin->getInstanceUrl();
    $row['key'] = '';
    $row['type'] = $plugin->label();
    $row['status'] = '';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    $operations['edit']['title'] = $this->t('Edit / Re-auth');
    // Having "destination" on edit link breaks OAuth.
    // Add a "revoke" action if we have a token.
    $operations['edit']['url'] = $entity->toUrl('edit-form');
    if (!$entity instanceof ApiSyncAuthConfigInterface
        || !$entity->hasLinkTemplate('revoke')
    ) {
      return $operations;
    }
    // Add a "revoke" action if we have a token.
    $operations['revoke'] = [
      'title' => $this->t('Revoke'),
      'weight' => 20,
      'url' => $this->ensureDestination($entity->toUrl('revoke')),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['default'] = [
      'data' => '',
    ];
    $header['label'] = [
      'data' => $this->t('Label'),
    ];
    $header['url'] = [
      'data' => $this->t('URL'),
    ];
    $header['key'] = [
      'data' => $this->t('Consumer Key'),
    ];
    $header['type'] = [
      'data' => $this->t('Auth Type'),
    ];
    $header['status'] = [
      'data' => $this->t('Token Status'),
    ];

    return $header + parent::buildHeader();
  }

}
