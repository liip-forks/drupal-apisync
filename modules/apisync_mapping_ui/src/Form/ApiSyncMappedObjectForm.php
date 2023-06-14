<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Form;

use Drupal\apisync\Event\ApiSyncErrorEvent;
use Drupal\apisync\Event\ApiSyncEvents;
use Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface;
use Drupal\apisync_mapping\ApiSyncMappingStorage;
use Drupal\apisync_mapping\Entity\ApiSyncMappedObjectTypeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * API Sync Mapping Form base.
 */
class ApiSyncMappedObjectForm extends ContentEntityForm {

  /**
   * Mapping entity storage service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappingStorage
   */
  protected ApiSyncMappingStorage $mappingStorage;

  /**
   * Mapped object storage service.
   *
   * @var \Drupal\apisync_mapping\ApiSyncMappedObjectStorageInterface
   */
  protected ApiSyncMappedObjectStorageInterface $mappedObjectStorage;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructor for a ApiSyncMappedObjectForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Bundle info service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
      EntityRepositoryInterface $entityRepository,
      EntityTypeBundleInfoInterface $entityTypeBundleInfo,
      TimeInterface $time,
      EventDispatcherInterface $eventDispatcher,
      RequestStack $requestStack,
      EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
    $this->eventDispatcher = $eventDispatcher;
    $this->request = $requestStack->getCurrentRequest();
    $this->entityTypeManager = $entityTypeManager;
    $this->mappingStorage = $entityTypeManager->getStorage('apisync_mapping');
    $this->mappedObjectStorage = $entityTypeManager->getStorage('apisync_mapped_object');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('entity.repository'),
        $container->get('entity_type.bundle.info'),
        $container->get('datetime.time'),
        $container->get('event_dispatcher'),
        $container->get('request_stack'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Include the parent entity on the form.
    $form = parent::buildForm($form, $form_state);

    if ($this->entity->isNew()) {
      $drupalEntity = $this->getDrupalEntityFromUrl();
      if ($drupalEntity) {
        $form['drupal_entity']['widget'][0]['target_type']['#default_value'] = $drupalEntity->getEntityTypeId();
        $form['drupal_entity']['widget'][0]['target_id']['#default_value'] = $drupalEntity;
      }
    }

    // Allow exception to bubble up here, because we shouldn't have got here if
    // there isn't a mapping.
    // If entity is not set, entity types are dependent on available mappings.
    $mappings = $this
      ->mappingStorage
      ->loadMultiple();

    // @todo Can we validate the mapping options based on selected mapped object type?
    if ($mappings) {
      $options = array_keys($mappings);
      // Filter options based on drupal entity type.
      $form['apisync_mapping']['widget']['#options'] = array_intersect_key(
          $form['apisync_mapping']['widget']['#options'],
          array_flip($options)
      );
    }

    $form['actions']['push'] = [
      '#type' => 'submit',
      '#value' => $this->t('Push'),
      '#weight' => 5,
      '#submit' => [[$this, 'submitPush']],
      '#validate' => [[$this, 'validateForm'], [$this, 'validatePush']],
    ];

    $form['actions']['pull'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pull'),
      '#weight' => 6,
      '#submit' => [[$this, 'submitPull']],
      '#validate' => [[$this, 'validateForm'], [$this, 'validatePull']],
    ];

    // Disable the delete button, as it leads to a duplicated profile,
    // if the mapped object only is deleted.
    $form['actions']['delete']['#access'] = FALSE;

    return $form;
  }

  /**
   * Verify that entity type and mapping agree.
   */
  public function validatePush(array &$form, FormStateInterface $formState): void {
    $drupalEntityArray = $formState->getValue(['drupal_entity', 0]);

    // Verify entity was given - required for push.
    if (empty($drupalEntityArray['target_id'])) {
      $formState->setErrorByName('drupal_entity][0][target_id', $this->t('Please specify an entity to push.'));
    }
  }

  /**
   * API Sync ID is required for a pull.
   */
  public function validatePull(array &$form, FormStateInterface $formState): void {
    // Verify API Sync ID was given - required for pull.
    $apisyncId = $formState->getValue(['apisync_id', 0, 'value'], FALSE);
    if (!$apisyncId) {
      $formState->setErrorByName('apisync_id', $this->t('Please specify a API Sync ID to pull.'));
    }
  }

  /**
   * Submit handler for "push" button.
   */
  public function submitPush(array &$form, FormStateInterface $formState): void {
    $drupalEntityArray = $formState->getValue(['drupal_entity', 0]);
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject */
    $mappedObject = $this->entity;
    $mappedObject
      ->set('drupal_entity', $drupalEntityArray)
      ->set(
          'apisync_mapping',
          $formState->getValue(['apisync_mapping', 0, 'target_id'])
      );

    $id = $formState->getValue(['apisync_id', 0, 'value'], FALSE);
    if ($id) {
      $mappedObject->set('apisync_id', $id);
    }
    else {
      $mappedObject->set('apisync_id', '');
    }

    // Push to remote.
    try {
      // Push calls save(), so this is all we need to do:
      $mappedObject->push();
    }
    catch (\Exception $e) {
      $mappedObject->delete();
      $this->eventDispatcher->dispatch(new ApiSyncErrorEvent($e), ApiSyncEvents::ERROR);
      $this->messenger()->addError($this->t(
          'Push failed with an exception: %exception',
          ['%exception' => $e->getMessage()]
      ));
      $formState->setRebuild();
      return;
    }

    $this->messenger()->addStatus('Push successful.');
    $formState->setRedirect('entity.apisync_mapped_object.canonical', ['apisync_mapped_object' => $mappedObject->id()]);
  }

  /**
   * Submit handler for "pull" button.
   */
  public function submitPull(array &$form, FormStateInterface $formState): void {
    $mappingId = $formState->getValue(['apisync_mapping', 0, 'target_id']);
    $id = $formState->getValue(['apisync_id', 0, 'value'], FALSE);
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $mappedObject */
    $mappedObject = $this->entity
      ->set('apisync_id', $id)
      ->set('apisync_mapping', $mappingId);
    // Create stub entity.
    $drupalEntityArray = $formState->getValue(['drupal_entity', 0]);
    if ($drupalEntityArray['target_id']) {
      $drupalEntity = $this->entityTypeManager
        ->getStorage($drupalEntityArray['target_type'])
        ->load($drupalEntityArray['target_id']);
      $mappedObject->set('drupal_entity', $drupalEntity);
    }
    else {
      $drupalEntity = $this->entityTypeManager
        ->getStorage($drupalEntityArray['target_type'])
        ->create(['apisync_pull' => TRUE]);
      $mappedObject->set('drupal_entity', NULL);
      $mappedObject->setDrupalEntityStub($drupalEntity);
    }

    try {
      // Pull from remote. Save first to pass local validation.
      $mappedObject->save();
      $mappedObject->pull();
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(new ApiSyncErrorEvent($e), ApiSyncEvents::ERROR);
      $this->messenger()->addError($this->t(
          'Pull failed with an exception: %exception',
          ['%exception' => $e->getMessage()]
      ));
      $formState->setRebuild();
      return;
    }

    $this->messenger()->addStatus($this->t(
        'Successfully pulled from <a href=":url">%url</a> with mapping %mappingId.',
        [
          ':url' => $mappedObject->getApiSyncUrl(),
          '%url' => $mappedObject->getApiSyncUrl(),
          '%mappingId' => $mappingId,
        ]
    ));
    $formState->setRedirect('entity.apisync_mapped_object.canonical', ['apisync_mapped_object' => $mappedObject->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState): void {
    $this->getEntity()->save();
    $this->messenger()->addStatus($this->t('The mapping has been successfully saved.'));
    $formState->setRedirect(
        'entity.apisync_mapped_object.canonical',
        ['apisync_mapped_object' => $this->getEntity()->id()]
    );
  }

  /**
   * Helper to fetch the contextual Drupal entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null|false
   *   The entity, or FALSE if route params are not set.
   */
  private function getDrupalEntityFromUrl(): EntityInterface|null|bool {
    // Fetch the current entity from context.
    $entityTypeId = $this->request->query->get('entity_type_id');
    $entityId = $this->request->query->get('entity_id');
    if (empty($entityId) || empty($entityTypeId)) {
      return FALSE;
    }
    return $this->entityTypeManager
      ->getStorage($entityTypeId)
      ->load($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $routeMatch, $entityTypeId) {
    if ($routeMatch->getRawParameter('apisync_mapped_object_type')) {
      // Create new mapped object entity with bundle if route param is set.
      $mappedObjectType = $routeMatch->getParameter('apisync_mapped_object_type');
      $bundleId = $mappedObjectType instanceof ApiSyncMappedObjectTypeInterface
        ? $mappedObjectType->id()
        : $mappedObjectType;

      $entityType = $this->entityTypeManager->getDefinition('apisync_mapped_object');
      $bundleKey = $entityType->getKey('bundle');

      $mappedObjectStorage = $this->entityTypeManager->getStorage('apisync_mapped_object');
      return $mappedObjectStorage->create([$bundleKey => $bundleId]);
    }

    return parent::getEntityFromRouteMatch($routeMatch, $entityTypeId);
  }

}
