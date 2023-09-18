<?php

declare(strict_types = 1);

namespace Drupal\apisync_pull\Commands;

use Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\OData\ODataClientInterface;
use Drupal\apisync_mapping\Commands\ApiSyncMappingCommandsBase;
use Drupal\apisync_mapping\Event\ApiSyncQueryEvent;
use Drupal\apisync_pull\QueueHandler;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ApiSyncPullCommands extends ApiSyncMappingCommandsBase {

  /**
   * Pull queue handler service.
   *
   * @var \Drupal\apisync_pull\QueueHandler
   */
  protected QueueHandler $pullQueue;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructor for a ApiSyncPullCommands object.
   *
   * @param \Drupal\apisync\OData\ODataClientInterface $client
   *   OData client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\apisync\ApiSyncAuthProviderPluginManagerInterface $authManager
   *   Auth plugin manager.
   * @param \Drupal\apisync_pull\QueueHandler $pullQueue
   *   Pull queue handler.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   Event dispatcher.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      ODataClientInterface $client,
      EntityTypeManagerInterface $entityTypeManager,
      ApiSyncAuthProviderPluginManagerInterface $authManager,
      QueueHandler $pullQueue,
      ContainerAwareEventDispatcher $eventDispatcher
  ) {
    parent::__construct($client, $entityTypeManager, $authManager);
    $this->pullQueue = $pullQueue;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_pull:pull-query
   */
  public function interactPullQuery(Input $input, Output $output): void {
    $this->interactPullMappings($input, $output, 'Choose a API Sync mapping', 'Pull All');
  }

  /**
   * Fetch a filename interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_pull:pull-file
   */
  public function interactPullFile(Input $input, Output $output): void {
    $file = $input->getArgument('file');
    if (empty($file)) {
      return;
    }
    if (!file_exists($file)) {
      $this->logger()->error('File does not exist');
      return;
    }

    $this->interactPullMappings($input, $output);
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_pull:pull-reset
   */
  public function interactPullReset(Input $input, Output $output): void {
    $this->interactPullMappings($input, $output, 'Choose a API Sync mapping', 'Reset All');
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @param \Symfony\Component\Console\Input\Input $input
   *   Input.
   * @param \Symfony\Component\Console\Output\Output $output
   *   Output.
   *
   * @hook interact apisync_pull:pull-set
   */
  public function interactPullSet(Input $input, Output $output): void {
    $this->interactPullMappings($input, $output, 'Choose a API Sync mapping', 'Set All');
  }

  /**
   * Given a mapping, enqueue records for pull from OData.
   *
   * Ignoring modification timestamp. This command is useful, for example, when
   * seeding content for a Drupal site prior to deployment.
   *
   * @param string $name
   *   Machine name of the OData Mapping for which to queue pull records.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Exception
   *
   * @option where
   *   A WHERE clause to add to the pull query. Default behavior is to
   *   query and pull all records.
   * @option start
   *   strtotime()able string for the start timeframe over which to pull, e.g.
   *   "-5 hours". If omitted, use the value given by the mapping's pull
   *   timestamp. Must be in the past.
   * @option stop
   *   strtotime()able string for the end timeframe over which to pull, e.g.
   *   "-5 hours". If omitted, defaults to "now". Must be "now" or earlier.
   * @option force-pull
   *   if given, force all queried records to be pulled regardless of updated
   *   timestamps. If omitted, only OData records which are newer than
   *   linked Drupal records will be pulled.
   * @usage drush aspq user
   *   Query and queue all records for "user" OData mapping.
   * @usage drush aspq user --where="Email like '%foo%' AND (LastName = 'bar'
   *   OR FirstName = 'bar')"
   *   Query and queue all records for "user" OData mapping with Email
   *   field containing the string "foo" and First or Last name equal to "bar"
   * @usage drush aspq
   *   Fetch and process all pull queue items
   * @usage drush aspq --start="-25 minutes" --stop="-5 minutes"
   *   Fetch updated records for all mappings between 25 minutes and 5 minutes
   *   old, and process them.
   * @usage drush aspq foo --start="-25 minutes" --stop="-5 minutes"
   *   Fetch updated records for mapping "foo" between 25 minutes and 5 minutes
   *   old, and process them.
   *
   * @command apisync_pull:pull-query
   * @aliases aspq,asiq,apisync-pull-query,apisync_pull:query
   */
  public function pullQuery(
      string $name,
      array $options = [
        'where' => '',
        'start' => 0,
        'stop' => 0,
        'force-pull' => FALSE,
      ]
  ): void {
    $mappings = $this->getPullMappingsFromName($name);
    $start = $options['start'] ? strtotime($options['start']) : 0;
    $stop = $options['stop'] ? strtotime($options['stop']) : 0;
    if ($start > $stop) {
      $this->logger()->error(dt('Stop date-time must be later than start date-time.'));
      return;
    }

    foreach ($mappings as $mapping) {

      if ($options['force-pull']) {
        $start = 1;
      }

      $query = $mapping->getPullQuery([], $start, $stop);
      if (!$query) {
        $this->logger()->error(dt(
            '!mapping: Unable to generate pull query. Does this mapping have any OData Action Triggers enabled?',
            ['!mapping' => $mapping->id()]
        ));
        continue;
      }

      if ($options['where']) {
        $query->addBuiltCondition([$options['where']]);
      }

      $this->eventDispatcher->dispatch(
          new ApiSyncQueryEvent($mapping, $query),
          ApiSyncEvents::PULL_QUERY
      );

      $this->logger()->info(dt('!mapping: Issuing pull query: !query', [
        '!query' => (string) $query,
        '!mapping' => $mapping->id(),
      ]));
      $results = $this->client->query($query);

      if ($results->size() === 0) {
        $this->logger()->warning(dt('!mapping: No records found to pull.', ['!mapping' => $mapping->id()]));
        return;
      }

      $this->pullQueue->enqueueAllResults($mapping, $results, $options['force-pull']);

      $this->logger()->info(dt('!mapping: Queued !count items for pull.', [
        '!count' => $results->size(),
        '!mapping' => $mapping->id(),
      ]));
    }
  }

  /**
   * Reset pull timestamps for one or all API Sync Mappings.
   *
   * @param string $name
   *   Mapping id.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @option delete
   *   Reset delete date timestamp (instead of pull date timestamp)
   * @usage drush as-pull-reset
   *   Reset pull timestamps for all mappings.
   * @usage drush as-pull-reset foo
   *   Reset pull timestamps for mapping "foo"
   * @usage drush as-pull-reset --delete
   *   Reset "delete" timestamps for all mappings
   * @usage drush as-pull-reset foo --delete
   *   Reset "delete" timestamp for mapping "foo"
   *
   * @command apisync_pull:pull-reset
   * @aliases as-pull-reset,apisync_pull:reset
   */
  public function pullReset(string $name, array $options = ['delete' => NULL]): void {
    $mappings = $this->getPullMappingsFromName($name);
    foreach ($mappings as $mapping) {
      if ($options['delete']) {
        $mapping->setLastDeleteTime(NULL);
      }
      else {
        $mapping->setLastPullTime(NULL);
      }
      /** @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface $mappedObjectStorage */
      $mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
      $mappedObjectStorage->setForcePull($mapping);
      $this->logger()->info(dt('Pull timestamp reset for !name', ['!name' => $name]));
    }
  }

  /**
   * Set a specific pull timestamp on a single API Sync Mapping.
   *
   * @param string $name
   *   Mapping id.
   * @param int $time
   *   Timestamp.
   * @param array $options
   *   Assoc array of options.
   *
   * @throws \Exception
   *
   * @option delete
   *   Reset delete date timestamp (instead of pull date timestamp)
   * @usage drush as-pull-set foo
   *   Set pull timestamps for mapping "foo" to "now"
   * @usage drush as-pull-set foo 1517416761
   *   Set pull timestamps for mapping "foo" to 2018-01-31T15:39:21+00:00
   *
   * @command apisync_pull:pull-set
   * @aliases as-pull-set,apisync_pull:set
   */
  public function pullSet(string $name, int $time, array $options = ['delete' => NULL]): void {
    $mappings = $this->getPullMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $mapping->setLastPullTime(NULL);
      if ($options['delete']) {
        $mapping->setLastDeleteTime($time);
      }
      else {
        $mapping->setLastPullTime($time);
      }
      $this->mappedObjectStorage->setForcePull($mapping);
      $this->logger()->info(dt('Pull timestamp reset for !name', ['!name' => $name]));
    }
  }

}
