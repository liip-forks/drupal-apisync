<?php

declare(strict_types = 1);

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

      // Encoding issue with name, cropped multibyte characters.
      if (is_string($value) && mb_strlen($value) !== strlen($value)) {
        $stringArray = str_split($value);
        // If the last character is broken and the second
        // last is a UTF-8 character. Cut off the last 2
        // character.
        if (mb_detect_encoding($stringArray[strlen($value) - 1]) === FALSE
            && mb_detect_encoding($stringArray[strlen($value) - 2]) === 'UTF-8'
        ) {
          $value = substr($value, 0, -2);
        }
      }
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
