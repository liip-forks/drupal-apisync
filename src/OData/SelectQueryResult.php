<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

use Drupal\apisync_mapping\Entity\ApiSyncMappingInterface;

/**
 * Select Query Result.
 */
class SelectQueryResult implements SelectQueryResultInterface {

  /**
   * Total number of records for this query.
   *
   * @var int
   */
  protected int $totalSize;

  /**
   * Indicates whether the current result set is the complete set.
   *
   * @var bool
   */
  protected bool $done;

  /**
   * The current result set.
   *
   * @var \Drupal\apisync\OData\ODataObjectInterface[]
   */
  protected array $records;

  /**
   * If there are additional records, the URL of the query to fetch them.
   *
   * @var string
   */
  protected string $nextRecordsUrl;

  /**
   * Constructor for a SelectQueryResult object.
   *
   * @param array $results
   *   The query results.
   */
  public function __construct(array $results) {
    $this->totalSize = count($results['value']);

    $this->done = TRUE;
    if (!empty($results['@odata.nextLink'])) {
      $this->done = FALSE;
      $this->nextRecordsUrl = $results['@odata.nextLink'];
    }
    $this->records = [];
    foreach ($results['value'] as $record) {
      $this->records[] = new ODataObject($record);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createSingle(ODataObjectInterface $record): static {
    $results = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [],
    ];
    $result = new static($results);
    $result->records[] = $record;
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function nextRecordsUrl(): ?string {
    return $this->nextRecordsUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function size(): int {
    return $this->totalSize;
  }

  /**
   * {@inheritdoc}
   */
  public function done(): bool {
    return $this->done;
  }

  /**
   * {@inheritdoc}
   */
  public function records(): array {
    return $this->records;
  }

  /**
   * {@inheritdoc}
   */
  public function record(string $apisyncId, ApiSyncMappingInterface $mapping): ODataObjectInterface|false {
    /** @var \Drupal\apisync_mapping\ApiSyncIdProviderInterface $apisyncIdProvider */
    $apisyncIdProvider = \Drupal::service('apisync_mapping.apisync_id_provider');
    foreach ($this->records as $record) {
      // This will throw exceptions if the wrong mapping is passed as key
      // mappings won't be present.
      try {
        $derivedId = $apisyncIdProvider->getApiSyncId($record, $mapping);
        if ($derivedId === $apisyncId) {
          return $record;
        }
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }
    return FALSE;
  }

}
