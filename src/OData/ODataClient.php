<?php

declare(strict_types = 1);

namespace Drupal\apisync\OData;

use Drupal\apisync\ApiSyncAuthProviderInterface;
use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Entity\ApiSyncAuthConfigInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * Objects, properties, and methods to communicate with the API Sync REST API.
 */
class ODataClient implements ODataClientInterface {

  use StringTranslationTrait;

  /**
   * Response object.
   *
   * @var \GuzzleHttp\Psr7\Response|null
   */
  public Response|null $response;

  /**
   * Remote API URL.
   *
   * @var \Drupal\Core\Url
   */
  protected Url $url;

  /**
   * API Sync immutable config object.  Useful for gets.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $immutableConfig;

  /**
   * Active auth provider.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderInterface|null
   */
  protected ?ApiSyncAuthProviderInterface $authProvider;

  /**
   * Active auth provider config.
   *
   * @var \Drupal\apisync\Entity\ApiSyncAuthConfigInterface|null
   */
  protected ?ApiSyncAuthConfigInterface $authConfig;

  /**
   * Active auth token.
   *
   * @var \OAuth\OAuth2\Token\TokenInterface|null
   */
  protected ?TokenInterface $authToken;

  /**
   * HTTP client options.
   *
   * @var array
   */
  protected array $httpClientOptions;

  protected const CACHE_LIFETIME = 300;

  protected const LONGTERM_CACHE_LIFETIME = 86400;

  /**
   * Constructor which initializes the consumer.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The GuzzleHttp Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Serialization\Json $json
   *   The JSON serializer service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Time service.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   The auth manager.
   */
  public function __construct(
      protected ClientInterface $httpClient,
      protected ConfigFactoryInterface $configFactory,
      protected StateInterface $state,
      protected CacheBackendInterface $cache,
      protected Json $json,
      protected TimeInterface $time,
      protected ApiSyncAuthProviderPluginManagerInterface $authManager
  ) {
    $this->immutableConfig = $this->configFactory->get('apisync.settings');
    $this->httpClientOptions = [];
    $this->authProvider = $authManager->getProvider();
    $this->authConfig = $authManager->getConfig();
    $this->authToken = $authManager->getToken();
  }

  /**
   * Get the short term cache lifetime.
   *
   * @return int
   *   The short term cache lifetime.
   */
  public function getShortTermCacheLifetime(): int {
    return $this->immutableConfig->get('short_term_cache_lifetime') ?? static::CACHE_LIFETIME;
  }

  /**
   * Get the long term cache lifetime.
   *
   * @return int
   *   The long term cache lifetime.
   */
  public function getLongTermCacheLifetime() {
    return $this->immutableConfig->get('long_term_cache_lifetime') ?? static::LONGTERM_CACHE_LIFETIME;
  }

  /**
   * {@inheritdoc}
   */
  public function isInit(): bool {
    if (!$this->authProvider || !$this->authManager) {
      return FALSE;
    }
    // If authToken is not set, try refreshing it before failing init.
    if (!$this->authToken) {
      $this->authToken = $this->authManager->refreshToken();
      return isset($this->authToken);
    }
    return TRUE;
  }

  /**
   * Make an API call.
   *
   * @param string $path
   *   The path to request. Should beginn with a leading slash.
   * @param array $params
   *   The parameters to provide.
   * @param string $method
   *   The request method. (GET, POST, PATCH, etc.)
   * @param bool $returnObject
   *   If TRUE, the response object will be returned.
   *   Overwise the response data is returned.
   *
   * @return \Drupal\apisync\OData\RestResponse|mixed
   *   The response object if $returnObject is set to TRUE,
   *   overwise response data.
   *
   * @throws \Drupal\apisync\OData\RestException
   *
   * @see apiHttpRequest()
   */
  protected function apiCall($path, array $params = [], string $method = 'GET', bool $returnObject = FALSE): mixed {
    if (!$this->isInit()) {
      throw new RestException(NULL, 'RestClient is not initialized.');
    }

    // $path should always start with a leading slash.
    $url = $this->authProvider->getInstanceUrl() . $path;

    try {
      // We don't currently support eTags. We add a wildcard here if needed.
      $headers = $method === 'PATCH' ? ['If-Match' => '*'] : [];
      $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method, $headers));
    }
    catch (RequestException $e) {
      // RequestException gets thrown for any response status but 2XX.
      $this->response = $e->getResponse();

      // Any exceptions besides 401 get bubbled up.
      if (!$this->response || $this->response->getStatusCode() != 401) {
          // This is likely not the best place to resolve the issue, but we need
          // to ensure that messages are UTF-8 encoded or loggers can trip up.
          $message = $e->getMessage();
          $encodedMessage = mb_convert_encoding($message, 'UTF-8');
        throw new RestException($this->response, $encodedMessage, $e->getCode(), $e);
      }
    }

    if ($this->response->getStatusCode() == 401) {
      // The session ID or OAuth token used has expired or is invalid: refresh
      // token. If refresh_token() throws an exception, or if apiHttpRequest()
      // throws anything but a RequestException, let it bubble up.
      $this->authToken = $this->authManager->refreshToken();
      try {
        $headers = $method === 'PATCH' ? ['If-Match' => '*'] : [];
        $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method, $headers));
      }
      catch (RequestException $e) {
        $this->response = $e->getResponse();
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if (empty($this->response)
        || ((int) floor($this->response->getStatusCode() / 100)) != 2
    ) {
      $code = $this->response->getStatusCode();
      $reason = $this->response->getReasonPhrase();

      throw new RestException(
          $this->response,
          "Unknown error occurred during API call \"$path\": status code $code : $reason"
      );
    }

    if ($returnObject) {
      return $this->response;
    }
    else {
      return $this->response->data;
    }
  }

  /**
   * Private helper to issue an API request.
   *
   * @param string $url
   *   Fully-qualified URL to resource.
   * @param array $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   * @param array $headers
   *   Request headers to send as name => value.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   *
   * @see httpRequest()
   */
  protected function apiHttpRequest(string $url, array $params, string $method, array $headers = []): Response {
    if (!$this->authToken) {
      throw new \Exception('Missing OAuth Token');
    }
    if ($this->authProvider->id() === 'basic_auth') {
      $headers += [
        'Authorization' => 'Basic ' . $this->authToken->getAccessToken(),
        'Content-type' => 'application/json',
      ];
    }
    else {
      $headers += [
        'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
        'Content-type' => 'application/json',
      ];
    }

    $data = NULL;
    if (!empty($params)) {
      $data = $this->json->encode($params);
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

  /**
   * Private helper to issue an XML API request.
   *
   * @param string $url
   *   Fully-qualified URL to resource.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function apiXmlHttpRequest(string $url): Response {
    if (!$this->authToken) {
      throw new \Exception('Missing OAuth Token');
    }

    if ($this->authProvider->id() === 'basic_auth') {
      $headers = [
        'Authorization' => 'Basic ' . $this->authToken->getAccessToken(),
        'Content-type' => 'application/json',
      ];
    }
    else {
      $headers = [
        'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
        'Content-type' => 'application/json',
      ];
    }

    $data = NULL;
    $args = NestedArray::mergeDeep(
        $this->httpClientOptions,
        [
          'headers' => $headers,
          'body' => $data,
        ]
    );

    /** @var \GuzzleHttp\Client $client */
    $client = $this->httpClient;
    return $client->GET($url, $args);
  }

  /**
   * Make a HTTP request and receive the raw content.
   *
   * @param string $url
   *   Fully-qualified URL to resource.
   *
   * @return string
   *   The raw response content.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   *
   * @see httpRequest()
   */
  protected function httpRequestRaw(string $url): string {
    if (!$this->authManager->getToken()) {
      throw new \Exception('Missing OAuth Token');
    }
    $headers = [
      'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $response = $this->httpRequest($url, NULL, $headers);
    return $response->getBody()->getContents();
  }

  /**
   * Make the HTTP request. Wrapper around drupal_http_request().
   *
   * @param string $url
   *   Path to make request from.
   * @param string $data
   *   The request body.
   * @param array $headers
   *   Request headers to send as name => value.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Request exception.
   */
  protected function httpRequest(string $url, string $data = NULL, array $headers = [], $method = 'GET'): Response {
    // Build the request, including path and headers. Internal use.
    $args = NestedArray::mergeDeep(
        $this->httpClientOptions,
        [
          'headers' => $headers,
          'body' => $data,
        ]
    );
    return $this->httpClient->$method($url, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOptions(array $options): static {
    $this->httpClientOptions = NestedArray::mergeDeep($this->httpClientOptions, $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOption(string $optionName, $optionValue): static {
    $this->httpClientOptions[$optionName] = $optionValue;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOptions(): array {
    return $this->httpClientOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOption(string $optionName): mixed {
    return $this->httpClientOptions[$optionName];
  }

  /**
   * Extract normalized error information from a RequestException.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   Exception object.
   *
   * @return array
   *   Error array with keys:
   *   * message
   *   * errorCode
   *   * fields
   */
  protected function getErrorData(RequestException $e): array {
    $response = $e->getResponse();
    $responseBody = $response->getBody()->getContents();
    $data = $this->json->decode($responseBody);
    if (!empty($data[0])) {
      $data = $data[0];
    }
    return $data;
  }

  /**
   * @defgroup apisync_apicalls Wrapper calls around core apiCall()
   */

  /**
   * {@inheritdoc}
   */
  public function objects(bool $reset = FALSE): array {
    // Use the cached data if we have it.
    $cache = $this->cache->get('odata:objects');
    if (!$reset && $cache) {
      $objects = $cache->data;
    }
    else {
      // Fetch the metadata from the oauth API.
      $url = $this->authProvider->getMetadataUrl();

      $result = new XMLResponse($this->apiXmlHttpRequest($url));
      $parser = new ODataMetadataParser($result->data);
      $objects = $parser->getSchemaProperties();
      $this->cache->set(
          'odata:objects',
          $objects,
          $this->getRequestTime() + $this->getShortTermCacheLifetime(),
          ['apisync']
      );
    }
    return $objects;
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function query(SelectQueryInterface $query): SelectQueryResultInterface {
    // Casting $query as a string calls SelectQuery::__toString().
    $queryParams = (string) $query;

    return new SelectQueryResult($this->apiCall('/' . $queryParams));
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function queryAll(SelectQueryInterface $query): SelectQueryResultInterface {
    return new SelectQueryResult($this->apiCall('/queryAll?q=' . (string) $query));
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function queryMore(SelectQueryResultInterface $results): SelectQueryResultInterface {
    if ($results->done()) {
      return new SelectQueryResult([
        'totalSize' => $results->size(),
        'done' => TRUE,
        'records' => [],
      ]);
    }

    $fullUrl = urldecode($results->nextRecordsUrl());
    $nextRecordsUrl = str_replace($this->authProvider->getInstanceUrl(), '', $fullUrl);
    return new SelectQueryResult($this->apiCall($nextRecordsUrl));
  }

  /**
   * {@inheritdoc}
   *
   * @see objects()
   */
  public function objectDescribe(string $name, bool $reset = FALSE): mixed {
    $objects = $this->objects($reset);
    return $objects[$name];
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function objectCreate(string $objectType, array $params): ODataObjectInterface {
    $response = $this->apiCall("/$objectType", $params, 'POST', TRUE);
    $data = $response->data;
    return new ODataObject($data);
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function objectUpdate(string $path, array $params): void {
    $this->apiCall($path, $params, 'PATCH');
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function objectRead(string $path): ODataObjectInterface {
    return new ODataObject($this->apiCall($path));
  }

  /**
   * {@inheritdoc}
   *
   * @see apiCall()
   */
  public function objectDelete(string $path, bool $throwException = FALSE): void {
    try {
      $this->apiCall($path, [], 'DELETE');
    }
    catch (RequestException $e) {
      if ($throwException || $e->getResponse()?->getStatusCode() !== 404) {
        throw $e;
      }
    }
  }

  /**
   * Returns REQUEST_TIME.
   *
   * @return int
   *   The REQUEST_TIME server variable.
   */
  protected function getRequestTime(): int {
    return $this->time->getRequestTime();
  }

}
