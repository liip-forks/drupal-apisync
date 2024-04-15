<?php

declare(strict_types=1);

namespace Drupal\apisync;

use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\apisync\Entity\ApiSyncAuthConfig;
use Drupal\apisync\Entity\ApiSyncAuthConfigInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * Auth provider plugin manager.
 */
class ApiSyncAuthProviderPluginManager extends DefaultPluginManager implements ApiSyncAuthProviderPluginManagerInterface {

  /**
   * Config from apisync.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * API Sync Auth storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected ConfigEntityStorageInterface $authStorage;

  /**
   * Active auth config.
   *
   * @var \Drupal\apisync\Entity\ApiSyncAuthConfigInterface|null
   */
  protected ?ApiSyncAuthConfigInterface $authConfig = NULL;

  /**
   * Active auth provider.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderInterface|null
   */
  protected ?ApiSyncAuthProviderInterface $authProvider = NULL;

  /**
   * Active credentials.
   *
   * @var \Drupal\apisync\Consumer\ApiSyncCredentialsInterface
   */
  protected ApiSyncCredentialsInterface $authCredentials;

  /**
   * Active auth token.
   *
   * @var \OAuth\OAuth2\Token\TokenInterface|null
   */
  protected ?TokenInterface $authToken = NULL;

  /**
   * Constructor for a ApiSyncAuthProviderPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
      \Traversable $namespaces,
      CacheBackendInterface $cacheBackend,
      ModuleHandlerInterface $moduleHandler,
      EntityTypeManagerInterface $entityTypeManager,
      ConfigFactoryInterface $configFactory
  ) {
    parent::__construct(
        'Plugin/ApiSyncAuthProvider',
        $namespaces,
        $moduleHandler,
        ApiSyncAuthProviderInterface::class
    );
    $this->alterInfo('apisync_auth_provider_info');
    $this->setCacheBackend($cacheBackend, 'apisync_auth_provider');
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $configFactory->get('apisync.settings');
  }

  /**
   * Wrapper for apisync_auth storage service.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   *   Storage for apisync_auth.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function authStorage(): ConfigEntityStorageInterface {
    if (empty($this->authStorage)) {
      $this->authStorage = $this->entityTypeManager->getStorage('apisync_auth');
    }
    return $this->authStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviders(): array {
    return $this->authStorage()->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function hasProviders(): bool {
    return $this->authStorage()->hasData();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ?ApiSyncAuthConfigInterface {
    if (!$this->authConfig) {
      $providerId = $this->config->get('apisync_auth_provider');
      if (empty($providerId)) {
        return NULL;
      }
      $this->authConfig = ApiSyncAuthConfig::load($providerId);
    }
    return $this->authConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider(): ?ApiSyncAuthProviderInterface {
    if (!$this->authProvider) {
      if (!$this->getConfig()) {
        return NULL;
      }
      $this->authProvider = $this->getConfig()->getPlugin();
    }
    return $this->authProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): ?TokenInterface {
    if (!$this->authToken) {
      $config = $this->getConfig();
      if ($config === NULL) {
        return NULL;
      }
      $provider = $config->getPlugin();
      if ($provider === NULL) {
        return NULL;
      }
      try {
        $this->authToken = $provider->getAccessToken();
      }
      catch (\Exception $e) {
        return NULL;
      }
    }
    return $this->authToken;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshToken(): ?TokenInterface {
    return $this->getToken();
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($pluginId, array $configuration = []): string {
    return 'broken';
  }

}
