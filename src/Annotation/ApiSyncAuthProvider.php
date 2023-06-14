<?php

declare(strict_types = 1);

namespace Drupal\apisync\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * ApiSyncAuthProvider annotation definition.
 */
class ApiSyncAuthProvider extends Plugin {

  /**
   * The plugin ID of the auth provider.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the key provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public Translation $label;

  /**
   * The credentials class specific to this provider.
   *
   * @var string
   */
  public string $credentials_class;

}
