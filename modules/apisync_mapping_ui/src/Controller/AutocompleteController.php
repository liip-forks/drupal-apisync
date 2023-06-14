<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\typed_data\DataFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete Controller.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Entity Field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * Typed data fetcher.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected DataFetcherInterface $dataFetcher;

  /**
   * Constructs a new AutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   Entity field manager.
   * @param \Drupal\typed_data\DataFetcherInterface $dataFetcher
   *   Data fetcher.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, DataFetcherInterface $dataFetcher) {
    $this->fieldManager = $fieldManager;
    $this->dataFetcher = $dataFetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('entity_field.manager'),
        $container->get('typed_data.data_fetcher')
    );
  }

  /**
   * Autocomplete.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object providing the autocomplete query parameter.
   * @param string $entity_type_id
   *   The entity type filter options by.
   * @param string $bundle
   *   The bundle of the entity to filter options by.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON results.
   */
  public function autocomplete(Request $request, string $entity_type_id, string $bundle): JsonResponse {
    $string = Html::escape(mb_strtolower($request->query->get('q')));
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $bundle);

    // Filter out EntityReference Items.
    foreach ($fieldDefinitions as $index => $fieldDefinition) {
      if ($fieldDefinition->getType() === 'entity_reference') {
        unset($fieldDefinitions[$index]);
      }
    }
    $results = $this
      ->dataFetcher
      ->autocompletePropertyPath($fieldDefinitions, $string);

    return new JsonResponse($results);
  }

}
