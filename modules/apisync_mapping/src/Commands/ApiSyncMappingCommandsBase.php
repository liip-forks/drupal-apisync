<?php

declare(strict_types=1);

namespace Drupal\apisync_mapping\Commands;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Commands\ApiSyncCommandsBase;
use Drupal\apisync\Commands\QueryResult;
use Drupal\apisync\Commands\QueryResultTableFormatter;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

/**
 * Shared command base for API Sync Drush commands.
 */
abstract class ApiSyncMappingCommandsBase extends ApiSyncCommandsBase {

  /**
   * API Sync Mapping storage handler.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Mapped Object storage handler.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Constructor for a ApiSyncMappingCommandsBase object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   OData client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   Auth provider plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $client,
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncAuthProviderPluginManagerInterface $authManager
  ) {
    parent::__construct($client, $entityTypeManager, $authManager);

    $this->mappingStorage = $entityTypeManager->getStorage('apisync_mapping');
    $this->mappedObjectStorage = $entityTypeManager->getStorage('apisync_mapped_object');
  }

  /**
   * Collect a API Sync mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   * @param string $message
   *   The choice message.
   * @param string|false $allOption
   *   The option to choose all, or FALSE if it should not be shown.
   * @param string|null $dir
   *   The direction. Either 'push' or 'pull'.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  protected function interactMapping(
      Input $input,
      Output $output,
      string $message = 'Choose a API Sync mapping',
      string|bool $allOption = FALSE,
      ?string $dir = NULL
  ): void {
    $name = $input->getArgument('name');
    if ($name) {
      if (strtoupper($name) == 'ALL') {
        $input->setArgument('name', 'ALL');
        return;
      }
      /** @var \Drupal\apisync_mapping\Entity\ApiSyncMapping $mapping */
      $mapping = $this->mappingStorage->load($name);
      if (!$mapping) {
        $this->logger()->error(dt('Mapping %name does not exist.', ['%name' => $name]));
      }
      elseif ($dir == 'push' && !$mapping->doesPush()) {
        $this->logger()->error(dt('Mapping %name does not push.', ['%name' => $name]));
      }
      elseif ($dir == 'pull' && !$mapping->doesPull()) {
        $this->logger()->error(dt('Mapping %name does not pull.', ['%name' => $name]));
      }
      else {
        return;
      }
    }
    if ($dir == 'pull') {
      $options = $this->mappingStorage->loadPullMappings();
    }
    elseif ($dir == 'push') {
      $options = $this->mappingStorage->loadPushMappings();
    }
    else {
      $options = $this->mappingStorage->loadMultiple();
    }
    $this->doMappingNameOptions($input, array_keys($options), $message, $allOption);

  }

  /**
   * Collect a API Sync mapping name, and set it to a "name" argument.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   * @param string $message
   *   The choice message.
   * @param string|false $allOption
   *   The option to choose all, or FALSE if it should not be shown.
   */
  protected function interactPushMappings(
      Input $input,
      Output $output,
      string $message = 'Choose a API Sync mapping',
      string|bool $allOption = FALSE
  ): void {
    $this->interactMapping($input, $output, $message, $allOption, 'push');
  }

  /**
   * Collect a API Sync mapping name, and set it to a "name" argument.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output.
   * @param string $message
   *   The choice message.
   * @param string|false $allOption
   *   The option to choose all, or FALSE if it should not be shown.
   */
  protected function interactPullMappings(
      Input $input,
      Output $output,
      string $message = 'Choose a API Sync mapping',
      string|bool $allOption = FALSE
  ): void {
    $this->interactMapping($input, $output, $message, $allOption, 'pull');
  }

  /**
   * Helper method to collect the choice from user, given a set of options.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param array $options
   *   An array of options.
   * @param string $message
   *   The choice message.
   * @param string|false $allOption
   *   The option to choose all, or FALSE if it should not be shown.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  protected function doMappingNameOptions(
      Input $input,
      array $options,
      string $message,
      string|false $allOption = FALSE
  ): void {
    $options = array_combine($options, $options);
    if ($allOption) {
      $options['ALL'] = $allOption;
    }
    $answer = $this->io()->choice($message, $options);
    if (!$answer) {
      throw new UserAbortException();
    }
    $input->setArgument('name', $answer);
  }

  /**
   * Given a mapping name (and optional direction), get an array of mappings.
   *
   * @param string $name
   *   'ALL' to load all mappings, or a mapping id.
   * @param string|null $dir
   *   'push', 'pull' or NULL to load limit mappings by push or pull types.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The mappings.
   *
   * @throws \Exception
   */
  protected function getMappingsFromName(string $name, ?string $dir = NULL): array {
    if ($name == 'ALL') {
      if ($dir == 'pull') {
        $mappings = $this->mappingStorage->loadPullMappings();
      }
      elseif ($dir == 'push') {
        $mappings = $this->mappingStorage->loadPushMappings();
      }
      else {
        $mappings = $this->mappingStorage->loadMultiple();
      }
    }
    else {
      /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface $mapping */
      $mapping = $this->mappingStorage->load($name);
      if ($dir == 'push' && !$mapping->doesPush()) {
        throw new \Exception(dt("Mapping !name does not push.", ['!name' => $name]));
      }
      elseif ($dir == 'pull' && !$mapping->doesPull()) {
        throw new \Exception(dt("Mapping !name does not pull.", ['!name' => $name]));
      }
      $mappings = [$mapping];
    }
    $mappings = array_filter($mappings);
    if (empty($mappings)) {
      if ($dir == 'push') {
        throw new \Exception(dt('No push mappings loaded'));
      }
      if ($dir == 'pull') {
        throw new \Exception(dt('No pull mappings loaded'));
      }
    }
    return $mappings;
  }

  /**
   * Given a mapping name, get an array of matching push mappings.
   *
   * @param string $name
   *   The mapping name.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The matching mappings.
   *
   * @throws \Exception
   */
  protected function getPushMappingsFromName(string $name): array {
    return $this->getMappingsFromName($name, 'push');
  }

  /**
   * Given a mappin gname, get an array of matching pull mappings.
   *
   * @param string $name
   *   The mapping name.
   *
   * @return \Drupal\apisync_mapping\Entity\ApiSyncMappingInterface[]
   *   The pull mappings.
   *
   * @throws \Exception
   */
  protected function getPullMappingsFromName(string $name): array {
    return $this->getMappingsFromName($name, 'pull');
  }

  /**
   * Pass-through helper to add appropriate formatters for a query result.
   *
   * @param \Drupal\apisync\Commands\QueryResult $query
   *   The query result.
   *
   * @return \Drupal\apisync\Commands\QueryResult
   *   The same, unchanged query result.
   */
  protected function returnQueryResult(QueryResult $query): QueryResult {
    $formatter = new QueryResultTableFormatter();
    $formatterManager = Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('table', $formatter);
    return $query;
  }

}
