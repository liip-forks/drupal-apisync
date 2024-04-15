<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Select Query Result Interface.
 */
interface SelectQueryResultInterface {

  /**
   * Convenience method a SelectQueryResult from a single OData record.
   *
   * @param \Drupal\apisync\OData\ODataObjectInterface $record
   *   The record to be created.
   *
   * @return \Drupal\apisync\OData\SelectQueryResultInterface
   *   A query result containing the given record.
   */
  public static function createSingle(ODataObjectInterface $record): static;

  /**
   * Next records URL getter.
   *
   * @return string|null
   *   The next record url, or null.
   */
  public function nextRecordsUrl(): ?string;

  /**
   * Size getter.
   *
   * @return int
   *   The query size. For a single-page query, will be equal to total.
   */
  public function size(): int;

  /**
   * Indicates whether the query is "done", or has more results to be fetched.
   *
   * @return bool
   *   Return FALSE if the query has more pages of results.
   */
  public function done(): bool;

  /**
   * The results.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface[]
   *   The result records.
   */
  public function records(): array;

  /**
   * Fetch a particular record given its api sync ID.
   *
   * @param string $apisyncId
   *   The API Sync ID.
   * @param \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping
   *   Mapping.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface|false
   *   The record, or FALSE if no record exists for given id.
   */
  public function record(string $apisyncId, ApiSyncMappingInterface $mapping): ODataObjectInterface|false;

}
