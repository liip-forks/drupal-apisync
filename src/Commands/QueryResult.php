<?php

declare(strict_types = 1);

namespace Drupal\apisync\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata;
use Drupal\apisync\OData\SelectQueryInterface;
use Drupal\apisync\OData\SelectQueryResultInterface;

/**
 * Adds structured metadata to RowsOfFieldsWithMetadata.
 */
class QueryResult extends RowsOfFieldsWithMetadata {

  /**
   * Size of query result.
   *
   * @var int
   */
  protected int $size;

  /**
   * Total records returned by query.
   *
   * @var int
   */
  protected int $total;

  /**
   * The query.
   *
   * @var \Drupal\apisync\OData\SelectQueryInterface
   */
  protected SelectQueryInterface $query;

  /**
   * Constructor for a QueryResult object.
   *
   * @param \Drupal\apisync\OData\SelectQueryInterface $query
   *   OData select query.
   * @param \Drupal\apisync\OData\SelectQueryResultInterface $queryResult
   *   OData select result.
   */
  public function __construct(SelectQueryInterface $query, SelectQueryResultInterface $queryResult) {
    $data = [];
    foreach ($queryResult->records() as $id => $record) {
      $data[$id] = $record->fields();
      if (!empty($data[$id]['@odata.etag'])) {
        unset($data[$id]['@odata.etag']);
      }
    }
    parent::__construct($data);
    $this->size = count($queryResult->records());
    $this->total = $queryResult->size();
    $this->query = $query;
  }

  /**
   * Getter for query size (total number of records returned).
   *
   * @return int
   *   The size.
   */
  public function getSize(): int {
    return $this->size;
  }

  /**
   * Getter for query total (total number of records available).
   *
   * @return int
   *   The total.
   */
  public function getTotal(): int {
    return $this->total;
  }

  /**
   * Getter for query.
   *
   * @return \Drupal\apisync\SelectQueryInterface
   *   The query.
   */
  public function getQuery(): SelectQueryInterface {
    return $this->query;
  }

  /**
   * Get a prettified query.
   *
   * @return string
   *   Strip '+' escaping from the query.
   */
  public function getPrettyQuery(): string {
    return str_replace('+', ' ', (string) $this->query);
  }

}
