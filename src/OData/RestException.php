<?php

declare(strict_types = 1);

namespace Drupal\apisync\OData;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Rest Exception.
 */
class RestException extends \RuntimeException implements ExceptionInterface {

  /**
   * The current Response.
   *
   * @var \Psr\Http\Message\ResponseInterface|null
   */
  protected ?ResponseInterface $response;

  /**
   * The response body.
   *
   * @var string|null
   */
  protected ?string $body = NULL;

  /**
   * Constructor for a RestException object.
   *
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   A response, if available.
   * @param string $message
   *   Message (optional).
   * @param int $code
   *   Erorr code (optional).
   * @param \Exception|null $previous
   *   Previous exception (optional).
   */
  public function __construct(
      ?ResponseInterface $response = NULL,
      $message = "",
      $code = 0,
      ?\Exception $previous = NULL
  ) {
    $this->response = $response;
    $message .= $this->getResponseBody();
    parent::__construct($message, $code, $previous);
  }

  /**
   * Getter.
   *
   * @return null|\Psr\Http\Message\ResponseInterface
   *   The response.
   */
  public function getResponse(): ?ResponseInterface {
    return $this->response;
  }

  /**
   * Getter.
   *
   * @return string|null
   *   The response body.
   */
  public function getResponseBody(): ?string {
    if ($this->body) {
      return $this->body;
    }
    if (!$this->response) {
      return NULL;
    }
    $body = $this->response->getBody();
    if ($body) {
      $this->body = $body->getContents();
      return $this->body;
    }
    return '';
  }

}
