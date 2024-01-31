<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping;

/**
 * Push actions.
 */
enum PushActions: string {
  case Create = 'create';
  case Update = 'update';
}
