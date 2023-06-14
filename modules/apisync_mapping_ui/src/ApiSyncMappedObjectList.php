<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for apisync_mapping entity.
 *
 * @ingroup apisync_mapping
 */
class ApiSyncMappedObjectList extends EntityListBuilder {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected UrlGeneratorInterface $urlGenerator;

  /**
   * Set entityIds to show a partial listing of mapped objects.
   *
   * @var array
   */
  protected array $entityIds;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entityType): static {
    return new static(
        $entityType,
        $container->get('entity_type.manager')->getStorage($entityType->id()),
        $container->get('url_generator')
    );
  }

  /**
   * Constructs a new ApiSyncMappedObjectList object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator.
   */
  public function __construct(
      EntityTypeInterface $entityType,
      EntityStorageInterface $storage,
      UrlGeneratorInterface $urlGenerator
  ) {
    parent::__construct($entityType, $storage);
    $this->urlGenerator = $urlGenerator;
  }

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('Manage the fields on the <a href="@adminlink">Mappings</a>.', [
        '@adminlink' => $this->urlGenerator->generateFromRoute('entity.apisync_mapping.list'),
      ]),
    ];
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the API Sync Mapped Object list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader(): array {
    $header['id'] = [
      'data' => $this->t('ID'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['mapped_entity'] = $this->t('Entity');
    $header['apisync_link'] = $this->t('API Sync Record');
    $header['mapping'] = [
      'data' => $this->t('Mapping'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['changed'] = [
      'data' => $this->t('Last Updated'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['id'] = $entity->id();
    $row['mapped_entity']['data'] = $entity->drupal_entity->first()->view();
    $row['apisync_link']['data'] = $entity->apisync_link->first()->view();
    $row['mapping']['data'] = $entity->apisync_mapping->first()->view();
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->changed->value);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity): array {
    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => -100,
      'url' => $entity->toUrl(),
    ];
    $operations += parent::getDefaultOperations($entity);
    return $operations;
  }

  /**
   * Set the given entity ids to show only those in a listing of mapped objects.
   *
   * @param array $ids
   *   The entity ids.
   *
   * @return static
   *   The current instance. ($this)
   */
  public function setEntityIds(array $ids): static {
    $this->entityIds = $ids;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    // If we're building a partial list, only query for those entities.
    if (!empty($this->entityIds)) {
      return $this->entityIds;
    }
    return parent::getEntityIds();
  }

}
