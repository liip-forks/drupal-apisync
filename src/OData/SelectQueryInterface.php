<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

/**
 * A OData select query interface.
 */
interface SelectQueryInterface {

  /**
   * Return the query as a string.
   *
   * @return string
   *   The url-encoded query string.
   */
  public function __toString(): string;

  /**
   * Add a condition to the query.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Condition value. If an array, it will be split into quote enclosed
   *   strings separated by commas inside of parenthesis. Note that the caller
   *   must enclose the value in quotes as needed by the remote API.
   *   NOTE: It is the responsibility of the caller to escape any single-quotes
   *   inside of string values.
   * @param string $operator
   *   Conditional operator. One of '=', '!=', '<', '>', 'LIKE, 'IN', 'NOT IN'.
   *
   * @return $this
   */
  public function addCondition(string $field, mixed $value, string $operator = '='): static;

  /**
   * Remove all conditions for a given field.
   *
   * @param string $field
   *   The field for which all conditions should be removed.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function removeAllConditionsForField(string $field): static;

  /**
   * Set the fields to select.
   *
   * @param array $fields
   *   Array of fields to be selected.
   */
  public function setFields(array $fields): void;

  /**
   * Add a single field to the list to be selected.
   *
   * @param string $field
   *   The field name.
   */
  public function addField(string $field): void;

  /**
   * Add a field to order by to the query.
   *
   * @param string $field
   *   The name of the field to order by.
   * @param string $direction
   *   The direction to order by. (e.g. ASC)
   */
  public function addOrder(string $field, string $direction): void;

  /**
   * Set the limit to be selected.
   *
   * @param int|null $limit
   *   The limit.
   */
  public function setLimit(?int $limit): void;

  /**
   * Add a pre-built filter condition to the query.
   *
   * @param string|array $condition
   *   The filter condition.
   *
   * @see addCondition()
   *   For an example on how conditions are built.
   */
  public function addBuiltCondition(string|array $condition): void;

}
