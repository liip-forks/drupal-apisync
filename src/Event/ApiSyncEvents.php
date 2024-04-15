<?php

declare(strict_types=1);

namespace Drupal\apisync\Event;

/**
 * Defines events for API Sync.
 *
 * @see \Drupal\apisync\Event\ApiSyncEvents
 */
final class ApiSyncEvents {

  /**
   * Dispatched before enqueueing or triggering an entity delete.
   *
   * Event listeners should call $event->disallowDelete() to prevent delete.
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncDeleteAllowedEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const DELETE_ALLOWED = 'apisync.delete_allowed';

  /**
   * Dispatched before enqueueing or triggering a push event.
   *
   * Event listeners should call $event->disallowPush() to prevent push.
   *
   * Previously hook_apisync_push_mapping_object_alter().
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncPushAllowedEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_ALLOWED = 'apisync.push_allowed';

  /**
   * Dispatched immediately before processing a push event.
   *
   * Useful for injecting business logic into a ApiSyncMappedObject record,
   * e.g. to change the API Sync ID before pushing to remote.
   *
   * Previously hook_apisync_push_mapping_object_alter().
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncPushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_MAPPING_OBJECT = 'apisync.push_mapping_object';

  /**
   * Dispatched after building params to push to remote.
   *
   * Allow modifying params before they're pushed to remote.
   * Previously hook_apisync_push_params_alter().
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncPushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_PARAMS = 'apisync.push_params';

  /**
   * Dispatched after successful push to remote endpoint.
   *
   * Previously Hook_apisync_push_success().
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncPushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_SUCCESS = 'apisync.push_success';

  /**
   * Dispatched after failed push to remote.
   *
   * Previously hook_apisync_push_fail().
   *
   * The event listener method receives a
   * \Drupal\apisync_mapping\Event\ApiSyncPushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_FAIL = 'apisync.push_fail';

  /**
   * Dispatched before querying endpoint to pull records.
   *
   * Previously hook_apisync_pull_select_query_alter().
   *
   * Subscribers receive a Drupal\apisync_mapping\Event\ApiSyncPullEvent
   * instance, via which Drupal\apisync\SelectQuery may be altered before
   * building API Sync Drupal\apisync_pull\PullQueueItem items.
   *
   * @Event
   *
   * @var string
   */
  const PULL_QUERY = 'apisync.pull_query';

  /**
   * Dispatched before mapping entity fields for a pull.
   *
   * Can be used, for example, to alter odata object retrieved from the
   * endpoint or to assign a different Drupal entity.
   *
   * Previously hook_apisync_pull_mapping_object_alter().
   *
   * Subscribers receive a Drupal\apisync_mapping\Event\ApiSyncPullEvent
   * instance. Listeners should throw an exception to prevent an item from being
   * pulled, per Drupal\Core\Queue\QueueWorkerInterface.
   *
   * @see \Drupal\Core\Queue\QueueWorkerInterface
   *
   * @Event
   *
   * @var string
   */
  const PULL_PREPULL = 'apisync.pull_prepull';

  /**
   * Dispatched before assigning Drupal entity values during pull.
   *
   * Pull analog to PUSH_PARAMS.
   *
   * Previously hook_apisync_pull_entity_value_alter().
   *
   * Subscribers receive a Drupal\apisync_mapping\Event\ApiSyncPullEvent
   * instance in order to modify pull field values or entities.
   *
   * @Event
   *
   * @var string
   */
  const PULL_ENTITY_VALUE = 'apisync.pull_entity_value';

  /**
   * Dispatched immediately prior to saving the pulled Drupal entity.
   *
   * After all fields have been mapped and values assigned, can be used, for
   * example, to override mapping fields or implement data transformations.
   * Final chance for subscribers to prevent creation or alter a Drupal entity
   * during pull. Post-save operations (insert/update) should rely on
   * hook_entity_update or hook_entity_insert().
   *
   * Previously hook_apisync_pull_entity_presave().
   *
   * Subscribers receive a Drupal\apisync_mapping\Event\ApiSyncPullEvent
   * instance.
   *
   * @Event
   *
   * @var string
   */
  const PULL_PRESAVE = 'apisync.pull_presave';

  /**
   * Dispatched when API Sync encounters a loggable, non-fatal error.
   *
   * Subscribers receive a Drupal\apisync\ApiSyncErrorEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const ERROR = 'apisync.error';

  /**
   * Dispatched when API Sync encounters a concerning, but non-error event.
   *
   * Subscribers receive a Drupal\apisync\ApiSyncWarningEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const WARNING = 'apisync.warning';

  /**
   * Dispatched when API Sync encounters a basic loggable event.
   *
   * Subscribers receive a Drupal\apisync\ApiSyncNoticeEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const NOTICE = 'apisync.notice';

}
