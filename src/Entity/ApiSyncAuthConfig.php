<?php

declare(strict_types=1);

namespace Drupal\apisync\Entity;

use Drupal\apisync\ApiSyncAuthProviderInterface;
use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Consumer\ApiSyncCredentialsInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a API Sync Auth entity.
 *
 * @ConfigEntityType(
 *   id = "apisync_auth",
 *   label = @Translation("API Sync Auth Config"),
 *   module = "apisync_auth",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\apisync\Controller\ApiSyncAuthListBuilder",
 *     "form" = {
 *       "default" = "Drupal\apisync\Form\ApiSyncAuthForm",
 *       "delete" = "Drupal\apisync\Form\ApiSyncAuthDeleteForm",
 *       "revoke" = "Drupal\apisync\Form\ApiSyncAuthRevokeForm"
 *      }
 *   },
 *   links = {
 *     "collection" = "/admin/config/apisync/authorize/list",
 *     "edit-form" = "/admin/config/apisync/authorize/edit/{apisync_auth}",
 *     "delete-form" = "/admin/config/apisync/authorize/delete/{apisync_auth}",
 *     "revoke" = "/admin/config/apisync/authorize/revoke/{apisync_auth}"
 *   },
 *   admin_permission = "authorize apisync",
 *   config_export = {
 *    "id",
 *    "label",
 *    "provider",
 *    "provider_settings"
 *   },
 * )
 */
class ApiSyncAuthConfig extends ConfigEntityBase implements ApiSyncAuthConfigInterface {

  use StringTranslationTrait;

  /**
   * Auth id. e.g. "oauth_full_sandbox".
   *
   * @var string
   */
  protected string $id;

  /**
   * Auth label. e.g. "OAuth Full Sandbox".
   *
   * @var string
   */
  protected string $label;

  /**
   * The auth provider for this auth config.
   *
   * @var string
   */
  protected string $provider = 'basic_auth';

  /**
   * Provider plugin configuration settings.
   *
   * @var array
   */
  protected array $provider_settings = [];

  /**
   * Auth manager.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface|null
   */
  protected ?ApiSyncAuthProviderPluginManagerInterface $manager = NULL;

  /**
   * The plugin provider.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderInterface|null
   */
  protected ?ApiSyncAuthProviderInterface $plugin = NULL;

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->id ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return $this->label ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): ?ApiSyncAuthProviderInterface {
    $this->provider = 'basic_auth';
    if (!$this->plugin) {
      $this->plugin = $this->provider
        ? $this->authManager()->createInstance($this->provider, $this->getProviderSettings())
        : NULL;
    }
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderSettings(): array {
    return $this->provider_settings + ['id' => $this->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): ?string {
    return $this->provider ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials(): ApiSyncCredentialsInterface|false {
    return $this->getPlugin() ? $this->getPlugin()->getCredentials() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authManager(): ApiSyncAuthProviderPluginManagerInterface {
    if (!$this->manager) {
      $this->manager = \Drupal::service("plugin.manager.apisync.auth_providers");
    }
    return $this->manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginsAsOptions(): array {
    foreach ($this->authManager()->getDefinitions() as $id => $definition) {
      if ($id == 'broken') {
        // Do not add the fallback provider.
        continue;
      }
      $options[$id] = ($definition['label']);
    }
    if (!empty($options)) {
      return ['' => $this->t('- Select -')] + $options;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'auth_provider' => new DefaultSingleLazyPluginCollection(
          $this->authManager(),
          $this->provider,
          $this->getProviderSettings()
      ),
    ];
  }

}
