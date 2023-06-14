<?php

declare(strict_types = 1);

namespace Drupal\apisync\OData;

/**
 * OData Object Interface.
 */
interface ODataObjectInterface {

  /**
   * Fields getter.
   *
   * @return array
   *   All OData fields.
   */
  public function fields(): array;

  /**
   * Check if a field is set.
   *
   * @param int|string $key
   *   The key of the field to check.
   *
   * @return bool
   *   TRUE if the field exists.
   */
  public function hasField(int|string $key): bool;

  /**
   * Get the corresponding field value for a given key.
   *
   * @param int|string $key
   *   The key of the field to check.
   *
   * @return mixed|null
   *   The value, or NULL if given $key is not set.
   *
   * @see hasField()
   */
  public function field(int|string $key): mixed;

  /**
   * To array.
   *
   * @return array
   *   Array of fields.
   */
  public function toArray(): array;

}
