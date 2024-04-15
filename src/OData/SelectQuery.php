<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

/**
 * Select Query.
 */
class SelectQuery implements SelectQueryInterface {

  /**
   * Fields to be selected.
   *
   * @var array
   */
  protected array $fields = [];

  /**
   * Order-by statements.
   *
   * @var array
   */
  protected array $order = [];

  /**
   * Objct type name, e.g. Contact, Account, etc.
   *
   * @var string
   */
  protected string $objectType;

  /**
   * Limit query result to this number.
   *
   * @var int|null
   */
  protected ?int $limit = NULL;

  /**
   * Condition statements.
   *
   * @var array
   */
  protected array $conditions = [];

  /**
   * Constructor for a SelectQuery object.
   *
   * @param string $objectType
   *   API Sync object type to query.
   */
  public function __construct($objectType = '') {
    $this->objectType = $objectType;
  }

  /**
   * {@inheritdoc}
   */
  public function addCondition(string $field, mixed $value, string $operator = '='): static {
    if (is_array($value)) {
      $value = "('" . implode("','", $value) . "')";

      // Set operator to IN if wasn't already changed from the default.
      if ($operator === '=') {
        $operator = 'IN';
      }
    }

    $this->conditions[] = [
      'field' => $field,
      'operator' => $operator,
      'value' => $value,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    $query = $this->objectType . '?';

    if ($this->fields) {
      $query .= '$select=' . implode(',', array_unique($this->fields));
    }
    else {
      $query .= '$select=*';
    }

    // Add $filter.
    if (count($this->conditions) > 0) {
      $where = [];
      foreach ($this->conditions as $condition) {

        // Order here is important as we are not replacing whole words.
        // We need to attempt the multi character replacements first.
        $condition = str_replace(
            ['!=', '<=', '>=', '=', '>', '<'],
            ['ne', 'le', 'ge', 'eq', 'gt', 'lt'],
            $condition
        );

        $where[] = implode(' ', $condition);
      }
      $query .= '&$filter=' . implode(' and ', $where);
    }

    if ($this->order) {
      $query .= '&$orderby=';
      $fields = [];
      foreach ($this->order as $field => $direction) {
        $fields[] = $field . ' ' . $direction;
      }
      $query .= implode(', ', $fields);
    }

    if ($this->limit) {
      $query .= '&$top=' . (int) $this->limit;
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllConditionsForField(string $field): static {
    $this->conditions = array_filter(
        $this->conditions,
        static fn($condition) => !isset($condition['field']) || $condition['field'] !== $field
    );
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields(array $fields): void {
    $this->fields = $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrder(string $field, string $direction): void {
    $this->order[$field] = $direction;
  }

  /**
   * {@inheritdoc}
   */
  public function setLimit(?int $limit): void {
    $this->limit = $limit;
  }

  /**
   * {@inheritdoc}
   */
  public function addField(string $field): void {
    $this->fields[$field] = $field;
  }

  /**
   * {@inheritdoc}
   */
  public function addBuiltCondition(string|array $condition): void {
    $this->conditions[] = $condition;
  }

}
