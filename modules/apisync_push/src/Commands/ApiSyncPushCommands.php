<?php

declare(strict_types=1);

namespace Drupal\apisync_push\Commands;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\Commands\ApiSyncMappingCommandsBase;
use Drupal\apisync_mapping\MappingConstants;
use Drupal\apisync_push\PushQueue;
use Drupal\commerce_order\OrderStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ApiSyncPushCommands extends ApiSyncMappingCommandsBase {

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Push queue service.
   *
   * @var \Drupal\apisync_push\PushQueue
   */
  protected PushQueue $pushQueue;

  /**
   * Constructor for a ApiSyncPushCommands object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   OData client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   ETM service.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   Auth plugin manager.
   * @param \Drupal\apisync_push\PushQueue $pushQueue
   *   Push queue service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $client,
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncAuthProviderPluginManagerInterface $authManager,
      PushQueue $pushQueue,
      Connection $database
  ) {
    parent::__construct($client, $entityTypeManager, $authManager);
    $this->pushQueue = $pushQueue;
    $this->database = $database;
  }

  /**
   * Collect a mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_push:push-queue
   */
  public function interactPushQueue(Input $input, Output $output): void {
    $this->interactPushMappings($input, $output, 'Choose a API Sync mapping', 'Push All');
  }

  /**
   * Collect a mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_push:push-unmapped
   */
  public function interactPushUnmapped(Input $input, Output $output): void {
    $this->interactPushMappings($input, $output, 'Choose a API Sync mapping', 'Push All');
  }

  /**
   * Collect a mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_push:requeue
   */
  public function interactRequeue(Input $input, Output $output): void {
    $this->interactPushMappings($input, $output, 'Choose a API Sync mapping', 'Push All');
  }

  /**
   * Process push queues for one or all API Sync Mappings.
   *
   * @param string $name
   *   Mapping name.
   *
   * @throws \Exception
   *
   * @usage drush aspushq
   *   Process all push queue items.
   * @usage drush aspushq foo
   *   Process push queue items for mapping "foo".
   *
   * @command apisync_push:push-queue
   * @aliases aspushq,aspm,as-push-queue,apisync_push:queue
   */
  public function pushQueue(string $name): void {
    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      // Process one mapping queue.
      $this->pushQueue->processQueue($mapping);
      $this->logger()->info(dt('Finished pushing !name', ['!name' => $mapping->label()]));
    }
  }

  /**
   * Requeue mapped entities for asynchronous push.
   *
   * Addresses the frequent need to re-push all entities for a given mapping.
   * Given a mapping, re-queue all the mapped objects to the API Sync push
   * queue. The push queue will not be processed by this command, and no data
   * will be pushed to remote. Run apisync_push:push-queue to proceess
   * the records queued by this command.
   *
   * NOTE: Existing push queue records will be replaced by this operation.
   *
   * @param string $name
   *   The Drupal machine name of the mapping for the entities.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Exception
   *   If the dummy call failed for other reasons than a missing table.
   *
   * @option ids
   *   If provided, only requeue the entities given by these ids.
   *   Comma-delimited.
   * @usage drush aspu foo
   *   Requeue all drupal entities mapped objects for mapping "foo".
   * @usage drush aspu foo --ids=1,2,3,4
   *   Requeue entities for mapping "foo" with ids 1, 2, 3, 4, if they exist.
   *
   * @command apisync_push:requeue
   * @aliases asrq,apisync-push-requeue
   * @see apisync_push:push-queue
   */
  public function requeue(string $name, array $options = ['ids' => '']): void {
    // Dummy call to create item, to ensure table exists.
    try {
      $this->pushQueue->createItem(NULL);
    }
    catch (\Exception $e) {

    }

    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $ids = array_filter(array_map('intval', explode(',', $options['ids'])));
      $mappingName = $mapping->id();
      $op = MappingConstants::APISYNC_MAPPING_SYNC_DRUPAL_UPDATE;
      $time = time();
      $insertQuery = "REPLACE INTO apisync_push_queue
          (name, entity_id, mapped_object_id, op, failures, expire, created, updated)
          (SELECT '$mappingName', drupal_entity__target_id, id, '$op', 0, 0, $time, $time
           FROM apisync_mapped_object
           WHERE apisync_mapping = '$mappingName' ";
      if (!empty($ids)) {
        $insertQuery .= " AND drupal_entity__target_id IN (" . implode(',', $ids) . ")";
      }
      $insertQuery .= ")";
      $this->database->query($insertQuery)->execute();
    }
  }

  /**
   * Push entities of a mapped type that are not linked to API Sync Objects.
   *
   * @param string $name
   *   The Drupal machine name of the mapping for the entities.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @option count
   *   The number of entities to try to sync. (Default is 50).
   * @usage drush aspu foo
   *   Push 50 drupal entities without mapped objects for mapping "foo"
   * @usage drush aspu foo --count=42
   *   Push 42 unmapped drupal entities without mapped objects for mapping "foo"
   *
   * @command apisync_push:push-unmapped
   * @aliases aspu,apisync-push-unmapped,apisync_push:unmapped
   */
  public function pushUnmapped($name, array $options = ['count' => 50]) {
    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $entityType = $mapping->get('drupal_entity_type');
      $entityStorage = $this->entityTypeManager->getStorage($entityType);
      $entityKeys = $this->entityTypeManager->getDefinition($entityType)->getKeys();
      $idKey = $entityKeys['id'];
      $bundleKey = empty($entityKeys['bundle']) ? FALSE : $entityKeys['bundle'];
      $query = $this->database->select($this->entityTypeManager->getDefinition($entityType)->getBaseTable(), 'b');
      $query->leftJoin(
          'apisync_mapped_object',
          'm',
          "b.$idKey = m.drupal_entity__target_id AND m.drupal_entity__target_type = '$entityType'"
      );
      if ($bundleKey) {
        $query->condition("b.$bundleKey", $mapping->get('drupal_bundle'));
      }
      $query->fields('b', [$idKey]);
      $query->isNull('m.drupal_entity__target_id');
      $results = $query->range(0, $options['count'])
        ->execute()
        ->fetchAllAssoc($idKey);
      // Avoid issues with order refresh that block the queue.
      if ($entityStorage instanceof OrderStorage) {
        $entities = [];
        foreach (array_keys($results) as $id) {
          $entities[] = $entityStorage->loadUnchanged($id);
        }
      }
      else {
        $entities = $entityStorage->loadMultiple(array_keys($results));
      }
      $log = [];
      foreach ($entities as $entity) {
        apisync_push_entity_crud($entity, 'push_create');
        $log[] = $entity->id();
      }
      $this->logger->info(dt(
          "!mapping: !count unmapped entities found and push to remote attempted. See logs for more details.",
          ['!count' => count($log), '!mapping' => $mapping->label()]
      ));
    }
  }

}
