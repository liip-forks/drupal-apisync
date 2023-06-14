<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync\OData\SelectQuery;
use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Provides a list of mapped object IDs to delete.
 */
class ApiSyncDeleteProvider implements ApiSyncDeleteProviderInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The OData client.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $oDataClient;

  /**
   * The API Sync ID provider.
   *
   * @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface
   */
  protected ApiSyncIdProviderInterface $apiSyncIdProvider;

  /**
   * The Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructor for a ApiSyncDeleteProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\apisync\OData\ODataClientInterface $oDataClient
   *   OData client.
   * @param \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apiSyncIdProvider
   *   API Sync ID provider.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   */
  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      Connection $database,
      ODataClientInterface $oDataClient,
      ApiSyncIdProviderInterface $apiSyncIdProvider,
      LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->oDataClient = $oDataClient;
    $this->apiSyncIdProvider = $apiSyncIdProvider;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getMappedObjectIdsToDelete(ApiSyncMappingInterface $mapping): array {
    $mappedObjectTable = $this->entityTypeManager
      ->getDefinition('apisync_mapped_object')
      ->getBaseTable();

    $query = $this->database->select($mappedObjectTable, 'm')
      ->fields('m', ['apisync_id', 'id'])
      ->distinct();

    if ($mapping !== NULL) {
      $query->condition('apisync_mapping', $mapping->id());
    }

    $ids = $query->execute()->fetchAllKeyed(0);

    $type = $mapping->getApiSyncObjectType();
    $remoteKeys = [];

    $mappedObjectType = $mapping->getRelatedApiSyncMappedObjectType();
    if ($mappedObjectType !== NULL) {
      foreach ($mappedObjectType->getKeyFieldMappings() as $keyFieldMapping) {
        $remoteKeys[] = $keyFieldMapping['apisync_field'];
      }
    }

    $toDelete = $ids;
    $query = new SelectQuery($type);
    $query->setFields($remoteKeys);

    $results = $this->oDataClient->query($query);

    foreach ($results->records() as $record) {
      $apiSyncId = $this->apiSyncIdProvider->getApiSyncId($record, $mapping);
      unset($toDelete[$apiSyncId]);
    }

    // Iterate all results.
    while (!$results->done()) {
      $results = $this->oDataClient->queryMore($results);
      foreach ($results->records() as $record) {
        $apisyncId = $this->apiSyncIdProvider->getApiSyncId($record, $mapping);
        unset($toDelete[$apisyncId]);
      }
    }

    if (empty($toDelete)) {
      $this->logger->info($this->t('No orphaned mapped objects found for type @type', ['@type' => $type]));
      return [];
    }
    return array_values($toDelete);
  }

}
