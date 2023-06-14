<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Commands;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncDeleteProviderInterface;
use Drupal\apisync_mapping\ApiSyncIdProviderInterface;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
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
class ApiSyncMappingCommands extends ApiSyncMappingCommandsBase {

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Mapepd objects to delete provider service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncDeleteProviderInterface
   */
  protected ApiSyncDeleteProviderInterface $apiSyncDeleteProvider;

  /**
   * Constructor for a ApiSyncMappingCommands object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   OData client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   Auth provider plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\apisync_mapping\ApiSyncDeleteProviderInterface $apiSyncDeleteProvider
   *   Mapped objects to delete provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $client,
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncAuthProviderPluginManagerInterface $authManager,
      ConfigFactoryInterface $configFactory,
      Connection $database,
      ApiSyncDeleteProviderInterface $apiSyncDeleteProvider
  ) {
    parent::__construct($client, $entityTypeManager, $authManager);
    $this->database = $database;
    $this->config = $configFactory->get('apisync.settings');
    $this->apiSyncDeleteProvider = $apiSyncDeleteProvider;
  }

  /**
   * Read a OData ID interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact odata:read-object
   */
  public function interactReadObject(Input $input, Output $output): void {
    if (!$input->getArgument('name')) {
      $this->interactMapping($input, $output, 'Choose a OData mapping', 'Select all');
    }
    if (!$input->getArgument('id')) {
      $answer = $this->io()->ask('Enter the OData id to fetch');
      if (!$answer) {
        throw new UserAbortException();
      }
      $input->setArgument('id', $answer);
    }
  }

  /**
   * Retrieve all the data for an object with a specific ID.
   *
   * @param string $name
   *   Id of the apisync mapping whose mapped objects should be purged.
   * @param string $id
   *   The ID of the object to read. If object uses multiple keys, provide
   *   a JSON encoded string of remote field name to value for each key.
   *   Use the option "--composite" so that this is parse correctly.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Exception
   *
   * @usage drush odata:read-object foo bar
   *   Will get the object with the ID bar with the mapping foo.
   * @usage drush odata:read-object foo '{"Type":"bar1","Category":"bar2"}' --composite
   *   Will get the object with the params Type=bar1,Category=bar2 with
   *   the mapping foo.
   *
   * @command odata:read-object
   * @aliases oro,odata-read-object
   */
  public function readObject(string $name, string $id, array $options = ['composite' => NULL]): void {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
    $mapping = $this->mappingStorage->load($name);

    if (!$mapping) {
      throw new \Exception('No mapping found with name ' . $name);
    }

    $mappedObjectType = $mapping->getRelatedApiSyncMappedObjectType();

    if ($mappedObjectType === NULL) {
      throw new \Exception('No mapped object type found for mapping ' . $name);
    }

    $objectType = $mapping->getApiSyncObjectType();
    if ($options['composite']) {
      $keys = Json::decode($id);
      $params = [];
      foreach ($mappedObjectType->getKeyFieldMappings() as $keyFieldMapping) {
        $fieldName = $keyFieldMapping['apisync_field'];
        $fieldValue = $keys[$keyFieldMapping['apisync_field']];
        if (!$fieldValue) {
          throw new \Exception(
            'The key ' . $keyFieldMapping['apisync_field'] . ' is required. Please add it to your input.'
          );
        }
        // Strings need to be enclosed in quotes.
        if ($keyFieldMapping['apisync_field_type'] === 'Edm.String') {
          $fieldValue = "'$fieldValue'";
        }
        $params[] = "$fieldName=$fieldValue";
      }
      $paramsString = implode(',', $params);
      $path = "/{$objectType}({$paramsString})";
    }
    else {
      if (count($mappedObjectType->getKeyFieldMappings()) > 1) {
        throw new \Exception(
          "More than one key is required for mapping with name $name. Please provide an array of composite keys."
        );
      }
      $path = "/{$objectType}({$id})";
    }

    $object = $this->client->objectRead($path);
    if ($object) {
      $this->output()->writeln(
        dt('!type with id !id', [
          '!type' => $objectType,
          '!id' => $id,
        ])
      );
      $this->output()->writeln(print_r($object->fields()), Output::OUTPUT_NORMAL);
    }
  }

  /**
   * Get a limit argument interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact apisync_mapping:prune-revisions
   */
  public function interactPrune(Input $input, Output $output): void {
    if ($input->getArgument('limit')) {
      return;
    }
    $configLimit = $this->config->get('limit_mapped_object_revisions');
    // These 2 lines give different results:
    while (TRUE) {
      $limit = $this->io()->ask(
          'Enter a revision limit (integer). All revisions beyond this limit will be deleted, oldest first',
          $configLimit
      );
      if (!$limit) {
        throw new UserAbortException();
      }
      elseif ($limit > 0) {
        $input->setArgument('limit', $limit);
        return;
      }
      else {
        $this->logger()->error('A positive integer limit is required.');
      }
    }
  }

  /**
   * Delete old revisions of Mapped Objects, based on revision limit settings.
   *
   * Useful if you have recently changed settings, or if you have just updated
   * to a version with prune support.
   *
   * @param int $limit
   *   If $limit is not specified,
   *   apisync.settings.limit_mapped_object_revisions is used.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command apisync_mapping:prune-revisions
   * @aliases asprune,apisync-prune-revisions
   */
  public function pruneRevisions(int $limit): void {
    $revisionTable = $this->entityTypeManager
      ->getDefinition('apisync_mapped_object')
      ->getRevisionTable();
    $ids = $this->database
      ->select($revisionTable, 'r')
      ->fields('r', ['id'])
      ->having('COUNT(r.id) > ' . $limit)
      ->groupBy('r.id')
      ->execute()
      ->fetchCol();
    if (empty($ids)) {
      $this->logger()->warning(dt(
          "No Mapped Objects with more than !limit revision(s). No action taken.",
          ['!limit' => $limit]
      ));
      return;
    }
    $this->logger()->info(
      dt(
        'Found !count mapped objects with excessive revisions. Will prune to revision(s) each. This may take a while.',
        [
          '!count' => count($ids),
          '!limit' => $limit,
        ]
      )
    );
    $total = count($ids);
    $i = 0;
    $buckets = ceil($total / 20);
    if ($buckets == 0) {
      $buckets = 1;
    }
    foreach ($ids as $id) {
      if ($i++ % $buckets == 0) {
        $this->logger()->info(
          dt(
              "Pruned !i of !total records.",
              [
                '!i' => $i,
                '!total' => $total,
              ]
          )
        );
      }
      /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject */
      $mappedObject = $this->mappedObjectStorage->load($id);
      if ($mappedObject) {
        $mappedObject->pruneRevisions($this->mappedObjectStorage);
      }
    }
  }

  /**
   * Interactively gather a apisync mapping name.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact apisync_mapping:purge-drupal
   */
  public function interactPurgeDrupal(Input $input, Output $output): void {
    $this->interactMapping($input, $output, 'Choose a OData mapping', 'Purge All');
  }

  /**
   * Interactively gather a apisync mapping name.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact apisync_mapping:purge-apisync
   */
  public function interactPurgeApiSync(Input $input, Output $output): void {
    $this->interactMapping($input, $output, 'Choose a OData mapping', 'Purge All');
  }

  /**
   * Interactively gather a apisync mapping name.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact apisync_mapping:purge-mapping
   */
  public function interactPurgeMapping(Input $input, Output $output): void {
    $this->interactMapping($input, $output, 'Choose a OData mapping', 'Purge All');
  }

  /**
   * Interactively gather a apisync mapping name.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *
   * @hook interact apisync_mapping:purge-all
   */
  public function interactPurgeAll(Input $input, Output $output): void {
    $this->interactMapping($input, $output, 'Choose a OData mapping', 'Purge All');
  }

  /**
   * Clean up Mapped Objects referencing missing Drupal entities.
   *
   * @param string $name
   *   Id of the apisync mapping whose mapped objects should be purged.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command apisync_mapping:purge-drupal
   * @aliases aspd,as-purge-drupal
   */
  public function purgeDrupal(string $name): void {
    $mappedObjectTable = $this->entityTypeManager
      ->getDefinition('apisync_mapped_object')
      ->getBaseTable();

    $query = $this->database
      ->select($mappedObjectTable, 'm')
      ->fields('m', ['drupal_entity__target_type'])
      ->distinct();
    if ($name && strtoupper($name) != 'ALL') {
      $query->condition('apisync_mapping', $name);
    }
    $entityTypeIds = $query
      ->execute()
      ->fetchCol();
    if (empty($entityTypeIds)) {
      $this->logger()->info('No orphaned mapped objects found by Drupal entities.');
      return;
    }

    foreach ($entityTypeIds as $entityId) {
      $query = $this->database
        ->select($mappedObjectTable, 'm')
        ->fields('m', ['id']);
      $query->condition('drupal_entity__target_type', $entityId);

      $entityType = $this->entityTypeManager->getDefinition($entityId);
      if ($entityType) {
        $idKey = $entityType->getKey('id');
        $query->addJoin("LEFT", $entityType->getBaseTable(), 'et', "et.$idKey = m.drupal_entity__target_id");
        $query->isNull("et.$idKey");
      }
      $mappedObjectIds = $query->execute()->fetchCol();
      if (empty($mappedObjectIds)) {
        $this->logger()->info('No orphaned mapped objects found for ' . $entityId . '.');
        continue;
      }
      $this->purgeConfirmAndDelete($mappedObjectIds, 'entity type: ' . $entityId);
    }
  }

  /**
   * Helper to confirm before destructive operation.
   *
   * @param array $objectIds
   *   The object IDs.
   * @param string $extra
   *   The extra.
   */
  protected function purgeConfirmAndDelete(array $objectIds, string $extra = ''): void {
    if (empty($objectIds)) {
      return;
    }
    $message = 'Delete ' . count($objectIds) . ' orphaned mapped objects';
    if ($extra) {
      $message .= ' for ' . $extra;
    }
    $message .= '?';
    if (!$this->io()->confirm($message)) {
      return;
    }

    // We delete the mapped object before the entity to avoid the potential of
    // the apisync_push.module attempting to push an update to the remote. This
    // is not needed as we are purging items locally that are not present
    // remotely.
    // @todo Consider batching this to avoid memory issues.
    $mappedObjects = $this->mappedObjectStorage->loadMultiple($objectIds);
    $entitiesToDelete = array_map(
        static fn(ApiSyncMappedObjectInterface $mappedObject) => $mappedObject->getMappedEntity(),
        $mappedObjects
    );
    $this->mappedObjectStorage->delete($mappedObjects);
    foreach ($entitiesToDelete as $entityToDelete) {
      $entityToDelete->delete();
    }
  }

  /**
   * Helper to gather object types by prefix.
   *
   * @return array
   *   Array of object types by prefix.
   */
  protected function objectTypesByPrefix(): array {
    $ret = [];
    $describe = $this->client->objects();
    foreach ($describe as $object) {
      $ret[$object['name']] = $object;
    }
    return $ret;
  }

  /**
   * Clean up Mapped Objects by deleting records referencing missing records.
   *
   * @param string $name
   *   Id of the apisync mapping whose mapped objects should be purged.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Database\InvalidQueryException
   *
   * @command apisync_mapping:purge-apisync
   * @aliases aspas,apisync-purge-apisync
   */
  public function purgeApiSync(string $name): void {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
    $mapping = $this->mappingStorage->load($name);

    // Return without action if $mapping is NULL.
    if ($mapping === NULL) {
      return;
    }

    $toDelete = $this->apiSyncDeleteProvider->getMappedObjectIdsToDelete($mapping);

    $this->purgeConfirmAndDelete(array_values($toDelete), 'Object type *' . $mapping->getApiSyncObjectType() . '*');
  }

  /**
   * Clean up Mapped Objects by deleting records referencing missing Mappings.
   *
   * @param string $name
   *   Mapping id.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command apisync:purge-mapping
   * @aliases aspmap,apisync-purge-mapping
   */
  public function purgeMapping(string $name): void {
    $mappedObjectTable = $this->entityTypeManager
      ->getDefinition('apisync_mapped_object')
      ->getBaseTable();

    $query = $this->database
      ->select($mappedObjectTable, 'm')
      ->fields('m', ['apisync_mapping'])
      ->distinct();
    if ($name && strtoupper($name) != 'ALL') {
      $query->condition('apisync_mapping', $name);
    }
    $mappingIds = $query
      ->execute()
      ->fetchCol();
    if (empty($mappingIds)) {
      $this->logger()->info('No orphaned mapped objects found by mapping.');
      return;
    }

    foreach ($mappingIds as $mappingId) {
      $mapping = $this->mappingStorage->load($mappingId);
      // If mapping loads successsfully, we assume the mapped object is OK.
      if ($mapping) {
        continue;
      }
      $query = $this->database
        ->select($mappedObjectTable, 'm')
        ->fields('m', ['id']);
      $query->condition('apisync_mapping', $mappingId);
      $mappedObjIds = $query->distinct()
        ->execute()
        ->fetchCol();
      $this->purgeConfirmAndDelete($mappedObjIds, 'missing mapping: ' . $mappingId);
    }
  }

  /**
   * Clean up Mapped Objects table.
   *
   * Clean by deleting any records which reference missing Mappings, Entities,
   * or OData records.
   *
   * @param string $name
   *   Id of the apisync mapping whose mapped objects should be purged.
   *
   * @command apisync_mapping:purge-all
   * @aliases aspall,apisync-purge-all
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function purgeAll(string $name): void {
    $this->purgeDrupal($name);
    $this->purgeApiSync($name);
    $this->purgeMapping($name);
  }

  /**
   * Get the API Sync ID Provider service.
   *
   * @return \Drupal\apisync_mapping\ApiSyncIdProviderInterface
   *   The API Sync ID Provider service.
   *
   * @throws \RuntimeException
   * @throws \Psr\Container\ContainerExceptionInterface
   */
  protected function getApiSyncIdProvider(): ApiSyncIdProviderInterface {
    return Drush::getContainer()->get('apisync_mapping.apisync_id_provider');
  }

}
