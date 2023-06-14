<?php

declare(strict_types = 1);

namespace Drupal\apisync_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * API Sync link to external record.
 *
 * @FieldType(
 *   id = "apisync_link",
 *   label = @Translation("API Sync Record"),
 *   description = @Translation("A link to the API sync record."),
 *   default_widget = "link_default",
 *   default_formatter = "link",
 *   list_class = "\Drupal\apisync_mapping\Plugin\Field\FieldType\ApiSyncLinkItemList",
 *   constraints = {
 *     "LinkType" = {},
 *     "LinkAccess" = {},
 *     "LinkExternalProtocols" = {},
 *     "LinkNotExistingInternal" = {}
 *   }
 * )
 */
class ODataLinkItem extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $fieldDefinition): array {
    $properties['uri'] = DataDefinition::create('uri')
      ->setLabel(t('URL'));
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(t('ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    /** @var \Drupal\apisync_mapping\Entity\ApiSyncMappedObjectInterface $entity */
    $entity = $this->getEntity();
    return $entity->isNew() || !$entity->apiSyncId();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $fieldDefinition): array {
    return [];
  }

}
