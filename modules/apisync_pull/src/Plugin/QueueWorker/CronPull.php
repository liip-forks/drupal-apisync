<?php

declare(strict_types = 1);

namespace Drupal\apisync_pull\Plugin\QueueWorker;

/**
 * A API Sync record puller that pulls on CRON run.
 *
 * @QueueWorker(
 *   id = "cron_apisync_pull",
 *   title = @Translation("API Sync Pull"),
 *   cron = {"time" = 180}
 * )
 */
class CronPull extends PullBase {

}
