<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Rest Response.
 */
class RestResponse extends Response {

  use StringTranslationTrait;

  /**
   * The original Response used to build this object.
   *
   * @var \GuzzleHttp\Psr7\Response
   * @see __get()
   */
  protected Response $response;

  /**
   * The json-decoded response body.
   *
   * @var mixed
   * @see __get()
   */
  protected mixed $data;

  /**
   * Constructor for a RestResponse object.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   A response.
   */
  public function __construct(ResponseInterface $response) {
    $this->response = $response;
    parent::__construct(
        $response->getStatusCode(),
        $response->getHeaders(),
        $response->getBody(),
        $response->getProtocolVersion(),
        $response->getReasonPhrase()
    );
    $this->handleJsonResponse();
  }

  /**
   * Magic getter method to return the given property.
   *
   * @param string $key
   *   The property name.
   *
   * @return mixed
   *   The property value.
   *
   * @throws \Exception
   *   If $key property does not exist.
   */
  public function __get(string $key): mixed {
    if (!property_exists($this, $key)) {
      throw new \Exception("Undefined property $key");
    }
    return $this->$key;
  }

  /**
   * Helper function to eliminate repetitive json parsing.
   *
   * @return static
   *   The current instance. ($this)
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  private function handleJsonResponse(): static {
    $this->data = '';
    $responseBody = $this->getBody()->getContents();
    if (empty($responseBody)) {
      return NULL;
    }

    // Allow any exceptions here to bubble up:
    try {
      $data = Json::decode($responseBody);
    }
    catch (InvalidDataTypeException $e) {
      throw new RestException($this, $e->getMessage(), $e->getCode(), $e);
    }

    if (empty($data) || !is_array($data)) {
      throw new RestException($this, 'Invalid response');
    }

    if (!empty($data['error'])) {
      throw new RestException($this, $data['error']);
    }

    if (!empty($data[0]) && count($data) == 1) {
      $data = $data[0];
    }

    if (!empty($data['error'])) {
      throw new RestException($this, $data['error']);
    }

    if (!empty($data['errorCode'])) {
      throw new RestException($this, $data['errorCode']);
    }
    $this->data = $data;
    return $this;
  }

}
