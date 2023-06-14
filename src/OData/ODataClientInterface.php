<?php

declare(strict_types = 1);

namespace Drupal\apisync\OData;

/**
 * OData Client Interface.
 */
interface ODataClientInterface {

  /**
   * Check if authentication is initialized.
   *
   * @return bool
   *   TRUE if authToken is set or could be refreshed.
   */
  public function isInit(): bool;

  /**
   * HttpClientOptions setter.
   *
   * @param array $options
   *   The new options. Merged with old options.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setHttpClientOptions(array $options): static;

  /**
   * Set a specific HTTP client option.
   *
   * @param string $optionName
   *   The key of the option to set.
   * @param mixed $optionValue
   *   The value to be set.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setHttpClientOption(string $optionName, mixed $optionValue): static;

  /**
   * HttpClientOptions getter.
   *
   * @return array
   *   The HTTP client options.
   */
  public function getHttpClientOptions(): array;

  /**
   * Get a specific HTTP client option.
   *
   * @param string $optionName
   *   The key of the option to get.
   *
   * @return mixed
   *   The option value.
   */
  public function getHttpClientOption(string $optionName): mixed;

  /**
   * Get the object schema from metadata URL.
   *
   * @param bool $reset
   *   Set to TRUE if cached value should be ignored.
   *
   * @return array
   *   The object schema.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function objects(bool $reset = FALSE): array;

  /**
   * Execute a query.
   *
   * @param \Drupal\apisync\OData\SelectQueryInterface $query
   *   The query to excecute.
   *
   * @return \Drupal\apisync\OData\SelectQueryResultInterface
   *   The query result.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function query(SelectQueryInterface $query): SelectQueryResultInterface;

  /**
   * Execute a all query.
   *
   * @param \Drupal\apisync\OData\SelectQueryInterface $query
   *   The query to excecute.
   *
   * @return \Drupal\apisync\OData\SelectQueryResultInterface
   *   The query result.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function queryAll(SelectQueryInterface $query): SelectQueryResultInterface;

  /**
   * Get the next page from a query result.
   *
   * @param \Drupal\apisync\OData\SelectQueryResultInterface $results
   *   The previous query result to go off.
   *
   * @return \Drupal\apisync\OData\SelectQueryResultInterface
   *   The next set of records.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function queryMore(SelectQueryResultInterface $results): SelectQueryResultInterface;

  /**
   * Get the schema of a specific object.
   *
   * @param string $name
   *   The name of the object to get.
   * @param bool $reset
   *   Set to TRUE to ignore cache and reload data from remote.
   *
   * @return mixed
   *   The schema of the object.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function objectDescribe(string $name, bool $reset = FALSE): mixed;

  /**
   * Create the object on the remote system.
   *
   * @param string $objectType
   *   Object type.
   * @param array $params
   *   Params to send.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface
   *   OData object received as the response.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function objectCreate(string $objectType, array $params): ODataObjectInterface;

  /**
   * Update the object on the remote system.
   *
   * @param string $path
   *   The update path.
   * @param array $params
   *   Params to send.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function objectUpdate(string $path, array $params): void;

  /**
   * Read an object on the remote system.
   *
   * @param string $path
   *   The read path.
   *
   * @return \Drupal\apisync\OData\ODataObjectInterface
   *   A OData object created from the response.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function objectRead(string $path): ODataObjectInterface;

  /**
   * Delete an object on the remote system.
   *
   * @param string $path
   *   The update path.
   * @param bool $throwException
   *   Ignore exceptions if set to FALSE.
   *
   * @throws \Drupal\apisync\OData\RestException
   */
  public function objectDelete(string $path, bool $throwException = FALSE): void;

}
