<?php

declare(strict_types=1);

namespace Drupal\apisync_logger\EventSubscriber;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncExceptionEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * API Sync Logger Subscriber.
 */
class ApiSyncLoggerSubscriber implements EventSubscriberInterface {

  const EXCEPTION_MESSAGE_PLACEHOLDER = '%type: @message in %function (line %line of %file).';

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Create a new API Sync Logger Subscriber.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ApiSyncEvents::ERROR => 'apiSyncException',
      ApiSyncEvents::WARNING => 'apiSyncException',
      ApiSyncEvents::NOTICE => 'apiSyncException',
    ];
  }

  /**
   * ApiSyncException event callback.
   *
   * @param \Drupal\apisync\Event\ApiSyncExceptionEvent $event
   *   The event.
   */
  public function apiSyncException(ApiSyncExceptionEvent $event): void {
    $logLevelSetting = $this->configFactory->get('apisync_logger.settings')->get('log_level');
    $eventLevel = $event->getLevel();
    // Only log events whose log level is greater or equal to min log level
    // setting.
    if ($logLevelSetting != ApiSyncEvents::NOTICE) {
      if ($logLevelSetting == ApiSyncEvents::ERROR && $eventLevel != RfcLogLevel::ERROR) {
        return;
      }
      if ($logLevelSetting == ApiSyncEvents::WARNING && $eventLevel == RfcLogLevel::NOTICE) {
        return;
      }
    }

    $exception = $event->getException();
    if ($exception) {
      $this->logger->log($event->getLevel(), self::EXCEPTION_MESSAGE_PLACEHOLDER, Error::decodeException($exception));
    }
    else {
      $this->logger->log($event->getLevel(), $event->getMessage(), $event->getContext());
    }
  }

}
