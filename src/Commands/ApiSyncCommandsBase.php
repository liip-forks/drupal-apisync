<?php

declare(strict_types = 1);

namespace Drupal\apisync\Commands;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

/**
 * Shared command base for API Sync Drush commands.
 */
abstract class ApiSyncCommandsBase extends DrushCommands {

  /**
   * The API Sync client.
   *
   * @var \Drupal\apisync\OData\ODataClientInterface
   */
  protected ODataClientInterface $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * API Sync Auth Provider plugin manager service.
   *
   * @var \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface
   */
  protected ApiSyncAuthProviderPluginManagerInterface $authManager;

  /**
   * Constructor for a ApiSyncCommandsBase object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   The API Sync client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   API Sync Auth Provider plugin manager service.
   */
  public function __construct(
      ODataClientInterface $client,
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncAuthProviderPluginManagerInterface $authManager
  ) {
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->authManager = $authManager;
  }

  /**
   * Collect a API Sync object name, and set it to "object" argument.
   *
   * NB: there's no actual validation done here against API Sync objects.
   * If there's a way to attach multiple hooks to one method, please patch this.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   The input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   The output. (Not currently used)
   * @param string $message
   *   The choice message displayed in the console.
   */
  protected function interactObject(Input $input, Output $output, string $message = 'Choose a OData object name'): void {
    if (!$input->getArgument('object')) {
      $objects = $this->client->objects();
      $answer = $this->io()->choice($message, array_combine(array_keys($objects), array_keys($objects)));
      if (!$answer) {
        throw new UserAbortException();
      }
      $input->setArgument('object', $answer);
    }
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
