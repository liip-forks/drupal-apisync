<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync\Event\ApiSyncNoticeEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for deleting a apisync_mapped_oject entity.
 *
 * @ingroup content_entity_example
 */
class ApiSyncMappedObjectDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
      EntityRepositoryInterface $entityRepository,
      EntityTypeBundleInfoInterface $entityTypeBundleInfo,
      TimeInterface $time,
      EventDispatcherInterface $eventDispatcher
    ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('entity.repository'),
        $container->get('entity_type.bundle.info'),
        $container->get('datetime.time'),
        $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete this mapped object?');
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the contact list.
   */
  public function getCancelUrl(): Url {
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. Event dispatcher service sends
   * API Sync notvie level event which logs notice.
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject */
    $mappedObject = $this->getEntity();
    $mappedEntity = $mappedObject->getMappedEntity();
    // The mapped object may not have been deleted immediately with the mapped
    // entity as a push_delete may be queued. It should still be possible to
    // manually delete the mapped object.
    if ($mappedEntity) {
        $formState->setRedirectUrl($mappedEntity->toUrl());
    }
    $args = ['@id' => $mappedObject->apisync_id->value];
    $message = $this->t('ApiSyncMappedObject @id deleted.', $args);
    $this->messenger()->addMessage($message);
    $this->eventDispatcher->dispatch(
        new ApiSyncNoticeEvent(NULL, (string) $message, $args),
        ApiSyncEvents::NOTICE
    );
    $mappedObject->delete();
  }

}
