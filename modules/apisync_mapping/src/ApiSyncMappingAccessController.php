<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the apisync_mapping entity.
 *
 * @see \Drupal\apisync_mapping\Entity\ApiSyncMapping.
 */
class ApiSyncMappingAccessController extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(
      EntityInterface $entity,
      $operation,
      AccountInterface $account
  ): AccessResultInterface {
    switch ($operation) {
      case 'view':
        return $account->hasPermission('view apisync mapping')
          ? AccessResult::allowed()
          : AccessResult::forbidden();

      default:
        return $account->hasPermission('administer apisync mapping')
          ? AccessResult::allowed()
          : AccessResult::forbidden();
    }
  }

}
