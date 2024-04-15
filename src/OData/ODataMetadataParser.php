<?php

declare(strict_types=1);

namespace Drupal\apisync\OData;

/**
 * Objects, properties, and methods to communicate with the Remote REST API.
 */
class ODataMetadataParser {

  /**
   * Array of the schema data.
   *
   * @var \SimpleXMLElement
   */
  public \SimpleXMLElement $schema;

  /**
   * Namespace of the schema.
   *
   * @var string
   */
  public string $namespace;

  /**
   * Array of enums.
   *
   * @var array
   */
  public array $enums = [];

  /**
   * Array of complex types.
   *
   * @var array
   */
  public array $complexTypes = [];

  /**
   * Array of entity types.
   *
   * @var array
   */
  public array $entityTypes = [];

  /**
   * Array of entity sets.
   *
   * @var array
   */
  public array $entitySets = [];

  /**
   * Constructor for a ODataMetadataParser object.
   *
   * @param string $xmlData
   *   The XML data.
   */
  public function __construct(string $xmlData) {
    /** @var \Drupal\Core\Config\ImmutableConfig $settings */
    $settings = \Drupal::service('config.factory')->get('apisync.settings');

    // Gets rid of all namespace definitions.
    $schemaDataXML = new \SimpleXMLElement($xmlData);
    $schema = $schemaDataXML
      ->children('http://docs.oasis-open.org/odata/ns/edmx')
      ->children('http://docs.oasis-open.org/odata/ns/edm')[0]
      ->asXML();
    $schema = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $schema);
    $schemaDataXML = new \SimpleXMLElement($schema);

    $this->schema = $schemaDataXML->xpath('//Schema')[0];
    $this->namespace = (string) $schemaDataXML->xpath('///Schema/@Namespace')[0];

    // Enums.
    if (!empty($this->schema->xpath('//Schema/EnumType'))) {
      // Can either be an array of enums or a single enum.
      $enums = $this->schema->xpath('//Schema/EnumType');
      foreach ($enums as $enum) {
        $values = [];
        foreach ($enum->Member as $member) {
          $values[(string) $member['Name']] = (string) $member['Value'];
        }
        $name = (string) $enum[0]['Name'];
        $this->enums[$name] = [
          'Name' => $name,
          'Values' => $values,
        ];
      }
    }

    // Complex types.
    if (!empty($this->schema->xpath('//Schema/ComplexType'))) {
      // Can either be an array of complex types or a single complex type.
      $complexTypes = $this->schema->xpath('//Schema/ComplexType');

      // Loop through complex types.
      foreach ($complexTypes as $complexType) {
        $name = (string) $complexType[0]['Name'];

        $properties = [];
        foreach ($complexType->Property as $property) {
          $properties[] = $property;
        }

        $this->complexTypes[$name] = [
          'Name' => $name,
          'Properties' => $this->extractProperties($properties, $complexType, []),
        ];
      }
    }

    // Entity types.
    if (!empty($this->schema->xpath('//Schema/EntityType'))) {
      // Can either be an array of complex types or a single complex type.
      $entityTypes = $this->schema->xpath('//Schema/EntityType');

      $allowlistEntityTypes = $settings->get('allowlist_entity_types');
      if (!empty($allowlistEntityTypes)) {
        $allowlist = preg_split('/\n|\r\n?/', $allowlistEntityTypes);

        $filteredList = [];
        foreach ($entityTypes as $entityType) {
          $name = (string) $entityType[0]['Name'];
          if (in_array($name, $allowlist)) {
            $filteredList[] = $entityType;
          }
        }
        if (count($filteredList)) {
          $entityTypes = $filteredList;
        }
      }

      // Loop through entity types.
      foreach ($entityTypes as $entityType) {
        $name = (string) $entityType['Name'];

        $keys = [];
        foreach ($entityType->Key->children() as $key) {
          $keys[] = (string) $key['Name'];
        }

        $properties = [];
        foreach ($entityType->Property as $property) {
          $properties[] = $property;
        }

        $this->entityTypes[$name] = [
          'Name' => $name,
          'Key' => $keys ?? [],
          'Properties' => $this->extractProperties($properties, $entityType, $keys),
        ];
      }
    }

    // Entity sets.
    if (!empty($this->schema->xpath('//Schema/EntityContainer/EntitySet'))) {
      // Can either be an array of complex types or a single complex type.
      $entitySets = $this->schema->xpath('//Schema/EntityContainer/EntitySet');

      $allowlistEntitySets = $settings->get('whitelist_entity_sets');
      if (!empty($allowlistEntitySets)) {
        $allowlist = preg_split('/\n|\r\n?/', $allowlistEntitySets);

        $filteredList = [];
        foreach ($entitySets as $entitySet) {
          $name = (string) $entitySet[0]['Name'];
          if (in_array($name, $allowlist)) {
            $filteredList[] = $entitySet;
          }
        }
        if (count($filteredList)) {
          $entitySets = $filteredList;
        }
      }

      // Loop through entity types.
      foreach ($entitySets as $entitySet) {
        $name = (string) $entitySet['Name'];
        $this->entitySets[$name] = [
          'Label' => $name,
          'Name' => $name,
          'Type' => str_replace($this->namespace . '.', '', (string) $entitySet['EntityType']),
        ];
      }
    }
  }

  /**
   * Extract schema properties from metadata file.
   *
   * @return array
   *   The schema properties.
   */
  public function getSchemaProperties(): array {
    $objects = [];

    foreach ($this->entitySets as $entitySetName => $entitySet) {

      // Add matching proprties from entity type.
      if (array_key_exists($entitySet['Type'], $this->entityTypes)) {
        $object['fields'] = [];
        $entityType = $this->entityTypes[$entitySet['Type']];
        $object['name'] = $entitySetName;
        $object['label'] = $entitySetName;

        // Extract entity field.
        foreach ($entityType['Properties'] as $property) {

          // Support for basic OData types. See list here:
          // https://www.odata.org/documentation/odata-version-2-0/overview/
          switch ($property['Type']) {
            case 'Edm.Boolean':
            case 'Edm.Byte':
            case 'Edm.DateTime':
            case 'Edm.Decimal':
            case 'Edm.Double':
            case 'Edm.Single':
            case 'Edm.Guid':
            case 'Edm.Int16':
            case 'Edm.Int32':
            case 'Edm.Int64':
            case 'Edm.SByte':
            case 'Edm.String':
            case 'Edm.Time':
            case 'Edm.DateTimeOffset':
            case 'Edm.Date':
              $object['fields'][$property['Name']] = $property;
          }

          // Support for complex types.
          if (array_key_exists($property['Type'], $this->complexTypes)) {
            $object['fields'][$property['Name']] = $property;
          }
        }
        $objects[$entitySetName] = $object;
      }
    }
    return $objects;
  }

  /**
   * Extracts properties from entities or complex types.
   *
   * @param \SimpleXMLElement[]|null $properties
   *   The properties.
   * @param \SimpleXMLElement $type
   *   The type.
   * @param array $keys
   *   An array of key property names.
   * @param bool $propertyPath
   *   The property path.
   *
   * @return array
   *   The list of properties.
   */
  protected function extractProperties(
      ?array $properties,
      \SimpleXMLElement $type,
      array $keys,
      bool $propertyPath = FALSE
  ): array {
    $propertyList = [];
    // Iterate through properties.
    foreach ($properties as $key => $property) {
      if (!empty($property)) {

        $propertyList[$key]['Name'] = (string) $property['Name'];

        if ($propertyPath) {
          $propertyList[$key]['Name'] = $type['Name'] . '.' . $property['Name'];
        }

        $odataType = (string) $property['Type'];

        // Support for lists: "Collection(Edm.String)".
        $isList = str_starts_with($odataType, 'Collection(');
        $propertyList[$key]['List'] = FALSE;
        if ($isList) {
          $odataType = substr($odataType, 11, -1);
          $propertyList[$key]['List'] = TRUE;
        }

        // Remove the namespace from the type.
        $odataType = str_replace($this->namespace, '', $odataType);
        $propertyList[$key]['Type'] = $odataType;
        $propertyList[$key]['Nullable'] = !empty($property['Nullable']) ? filter_var(
            (string) $property['Nullable'],
            FILTER_VALIDATE_BOOLEAN
        ) : TRUE;

        // Check if the property is a key.
        $propertyList[$key]['Key'] = in_array((string) $property['Name'], $keys);
      }
    }
    return $propertyList;
  }

}
