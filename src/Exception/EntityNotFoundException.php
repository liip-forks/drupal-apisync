<?php

declare(strict_types=1);

namespace Drupal\apisync\Exception;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * EntityNotFoundException extends \Drupal\apisync\Exception\Exception.
 *
 * Thrown when a mapped entity cannot be loaded.
 */
class EntityNotFoundException extends Exception {

  use StringTranslationTrait;

  /**
   * A list of entity properties, for logging.
   *
   * @var mixed
   */
  protected mixed $entityProperties;

  /**
   * Entity type id, for logging.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * Constructor for a EntityNotFoundException object.
   *
   * @param mixed $entityProperties
   *   Entity properties.
   * @param string $entityTypeId
   *   Entity type id.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct($entityProperties, string $entityTypeId, \Throwable $previous = NULL) {
    parent::__construct(
        (string) $this->t('Entity not found. type: %type properties: %props', [
          '%type' => $entityTypeId,
          '%props' => var_export($entityProperties, TRUE),
        ]),
        0,
        $previous
    );
    $this->entityProperties = $entityProperties;
    $this->entityTypeId = $entityTypeId;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   The entityProperties.
   */
  public function getEntityProperties(): mixed {
    return $this->entityProperties;
  }

  /**
   * Getter.
   *
   * @return string
   *   The entityTypeId.
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Get a formattable message.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The message.
   */
  public function getFormattableMessage(): FormattableMarkup {
    return new FormattableMarkup('Entity not found. type: %type properties: %props', [
      '%type' => $this->entityTypeId,
      '%props' => var_export($this->entityProperties, TRUE),
    ]);
  }

}
