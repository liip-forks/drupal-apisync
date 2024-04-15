<?php

declare(strict_types=1);

namespace Drupal\apisync\Exception;

use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Exception.
 *
 * Thrown as a generic API Sync Exception.
 * Try to use more specifc Exception when possible.
 */
class Exception extends \RuntimeException implements ExceptionInterface {

}
