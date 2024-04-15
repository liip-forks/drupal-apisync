<?php

declare(strict_types=1);

namespace Drupal\apisync\Exception;

/**
 * Class IdentityNotFoundException extends Runtime Exception.
 *
 * Thrown when an auth provider does not have a properly initialized identity.
 */
class IdentityNotFoundException extends \RuntimeException {

}
