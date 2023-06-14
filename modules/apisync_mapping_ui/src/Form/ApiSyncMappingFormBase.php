<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface;
use Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * API Sync Mapping Form base.
 */
abstract class ApiSyncMappingFormBase extends EntityForm {

  /**
   * Field plugin manager.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager
   */
  protected ApiSyncMappingFieldPluginManager $mappingFieldPluginManager;

  /**
   * API Sync client.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $client;

  /**
   * Mappable types service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface
   */
  protected ApiSyncMappableEntityTypesInterface $mappableEntityTypes;

  /**
   * The mapping entity for this form.
   *
   * Not directly typed to match parent class.
   *
   * @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface
   */
  protected $entity;

  /**
   * Bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * Constructor for a ApiSyncMappingFormBase object.
   *
   * @param \Drupal\apisync_mapping\ApiSyncMappingFieldPluginManager $mappingFieldPluginManager
   *   Mapping plugin manager.
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   Rest client.
   * @param \Drupal\apisync_mapping\ApiSyncMappableEntityTypesInterface $mappableEntityTypes
   *   Mappable types.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info service.
   */
  public function __construct(
      ApiSyncMappingFieldPluginManager $mappingFieldPluginManager,
      ODataClientInterface $client,
      ApiSyncMappableEntityTypesInterface $mappableEntityTypes,
      EntityTypeBundleInfoInterface $bundleInfo
  ) {
    $this->mappingFieldPluginManager = $mappingFieldPluginManager;
    $this->client = $client;
    $this->mappableEntityTypes = $mappableEntityTypes;
    $this->bundleInfo = $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('plugin.manager.apisync_mapping_field'),
        $container->get('apisync.odata_client'),
        $container->get('apisync_mapping.mappable_entity_types'),
        $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Test the API Sync connection by issuing the given api call.
   *
   * @param string $method
   *   Which method to test on the API Sync client. Defaults to "objects".
   * @param mixed $arg
   *   An argument to send to the test method. Defaults to empty array.
   *
   * @return bool
   *   TRUE if API Sync endpoint (or cache) responded correctly.
   */
  protected function ensureConnection(
      string $method = 'objects',
      mixed $arg = [[], TRUE]
  ): bool {
    $message = '';
    if ($this->client->isInit()) {
      try {
        call_user_func_array([$this->client, $method], $arg);
        return TRUE;
      }
      catch (\Exception $e) {
        // Fall through.
        $message = $e->getMessage() ?: get_class($e);
      }
    }

    $href = new Url('apisync.auth_config');
    $this->messenger()
      ->addError(
          $this->t(
              'Error when connecting to the OData source. Please <a href="@href">check your credentials</a> and try again: %message',
              [
                '@href' => $href->toString(),
                '%message' => $message,
              ]
          ),
          'error'
      );
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    if (!$this->entity->save()) {
      $this->messenger()->addError($this->t('An error occurred while trying to save the mapping.'));
      return;
    }

    $this->messenger()->addStatus($this->t('The mapping has been successfully saved.'));
  }

  /**
   * Retreive API Sync's information about an object type.
   *
   * @param string $apisyncObjectType
   *   The object type of whose records you want to retreive.
   *
   * @return array
   *   Information about the remote object as provided by API Sync.
   *
   * @throws \Exception
   *   If $apisyncObjectType is not provided and
   *   $this->entity->apisync_object_type is not set.
   */
  protected function getApiSyncObject(string $apisyncObjectType = ''): array {
    if (empty($apisyncObjectType)) {
      $apisyncObjectType = $this->entity->get('apisync_object_type');
    }
    if (empty($apisyncObjectType)) {
      throw new \Exception('API Sync object type not set.');
    }
    // No need to cache here: ApiSync::objectDescribe implements caching.
    return $this->client->objectDescribe($apisyncObjectType);
  }

  /**
   * Helper to retreive a list of object type options.
   *
   * @return array
   *   An array of values keyed by machine name of the object with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getApiSyncObjectTypeOptions(): array {
    $apisyncObjectOptions = [];

    $apisyncObjects = $this->client->objects();
    foreach ($apisyncObjects as $object) {
      $apisyncObjectOptions[$object['name']] = $object['label'] . ' (' . $object['name'] . ')';
    }
    asort($apisyncObjectOptions);
    return $apisyncObjectOptions;
  }

}
