<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the comment entity.
 *
 * @see \Drupal\comment\Entity\Comment.
 */
class ApiSyncMappedObjectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(
      EntityInterface $entity,
      $operation,
      AccountInterface $account
  ): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer apisync');
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(
      AccountInterface $account,
      array $context,
      $entityBundle = NULL
  ): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer apisync');
  }

}
