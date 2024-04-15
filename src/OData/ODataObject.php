<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

/**
 * OData Object.
 */
class ODataObject implements ODataObjectInterface {

  /**
   * Key-value array of record fields.
   *
   * @var array
   */
  protected array $fields;

  /**
   * Constructor for a ODataObject object.
   *
   * @param array $data
   *   The OData field data.
   */
  public function __construct(array $data = []) {
    $this->fields = [];
    foreach ($data as $key => $value) {
      $this->fields[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function hasField(int|string $key): bool {
    return array_key_exists($key, $this->fields);
  }

  /**
   * {@inheritdoc}
   */
  public function field(int|string $key): mixed {
    if (!array_key_exists($key, $this->fields)) {
      return NULL;
    }
    return $this->fields[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->fields;
  }

}
