<?php

declare(strict_types = 1);

namespace Drupal\apisync\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\apisync\OData\SelectQuery;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

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
class ApiSyncCommands extends ApiSyncCommandsBase {

  /**
   * List the objects that are available in your organization.
   *
   * @command odata:list-objects
   * @aliases olo,odata-list-objects
   * @field-labels
   *   activateable: Activateable
   *   createable: Createable
   *   custom: Custom
   *   customSetting: CustomSetting
   *   deletable: Deletable
   *   deprecatedAndHidden: DeprecatedAndHidden
   *   feedEnabled: FeedEnabled
   *   hasSubtypes: HasSubtypes
   *   isSubtype: IsSubtype
   *   keyPrefix: KeyPrefix
   *   label: Label
   *   labelPlural: LabelPlural
   *   layoutable: Layoutable
   *   mergeable: Mergeable
   *   mruEnabled: MruEnabled
   *   name: Name
   *   queryable: Queryable
   *   replicateable: Replicateable
   *   retrieveable: Retrieveable
   *   searchable: Searchable
   *   triggerable: Triggerable
   *   undeletable: Undeletable
   *   updateable: Updateable
   *   urls: URLs
   * @default-fields name,label,labelPlural
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The objects.
   *
   * @throws \Exception
   */
  public function listObjects(): RowsOfFields {
    $objects = $this->client->objects();
    if (!empty($objects)) {
      foreach ($objects as $name => $object) {
        $rows[$name] = $object;
      }
      return new RowsOfFields($rows);
    }
    throw new \Exception('Could not load any information about available objects.');
  }

  /**
   * Wrap ::interactObject for describe-fields.
   *
   * @hook interact odata:describe-fields
   */
  public function interactDescribeFields(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Retrieve all the metadata for an object.
   *
   * @param string $object
   *   The object name in OData.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The fields, or null if the object was not found.
   *
   * @throws \Exception
   *
   * @command odata:describe-fields
   * @aliases odata:describe-object,odo,odf,odata-describe-fields
   * @usage drush odo Contact
   *   Show metadata about Contact Object type.
   *
   * @field-labels
   *   Label: Label
   *   Name: Name
   *   Type: Type
   *   List: List
   *   Key: Key
   *   Nullable: Nullable
   *
   * @default-fields Name,Type
   */
  public function describeFields(string $object): ?RowsOfFields {
    $objectDescription = $this->client->objectDescribe($object);
    // Return if we cannot load any data.
    if (empty($objectDescription)) {
      $this->logger()->error(dt('Could not load data for object !object', ['!object' => $object]));
      return NULL;
    }

    foreach ($objectDescription['fields'] as $field => $data) {
      $rows[$field] = $data;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Wrap ::interactObject() for query-object.
   *
   * @hook interact odata:query-object
   */
  public function interactQueryObject(Input $input, Output $output) {
    return $this->interactObject($input, $output, 'Enter the object to be queried');
  }

  /**
   * Query an object with specified conditions.
   *
   * @param string $object
   *   The object type name in API Sync (e.g. Account).
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @return \Drupal\apisync\Commands\QueryResult
   *   The query result.
   *
   * @throws \Exception
   *
   * @option where
   *   A WHERE clause to add to the query
   * @option fields
   *   A comma-separated list fields to select in the query. If absent, an
   *   API call is used to find all fields
   * @option limit
   *   Integer limit on the number of results to return for the query.
   * @option order
   *   Comma-separated fields by which to sort results. Make sure to enclose in
   *   quotes for any whitespace.
   *
   * @command odata:query-object
   * @aliases oqo,odo-query-object
   */
  public function queryObject(string $object, array $options = [
    'format' => 'table',
    'where' => NULL,
    'fields' => NULL,
    'limit' => 10,
    'order' => NULL,
  ]): QueryResult {
    $query = new SelectQuery($object);

    if (!$options['fields']) {
      $object = $this->client->objectDescribe($object);
      $query->setFields(array_keys($object['fields']));
    }
    else {
      $query->setFields(explode(',', $options['fields']));
    }

    $query->setLimit($options['limit']);

    if ($options['where']) {
      $query->addBuiltCondition([$options['where']]);
    }

    if ($options['order']) {
      $orders = explode(',', $options['order']);
      foreach ($orders as $order) {
        [$field, $dir] = preg_split('/\s+/', $order, 2);
        $query->addOrder($field, $dir);
      }
    }
    return $this->returnQueryResult(new QueryResult($query, $this->client->query($query)));
  }

  /**
   * Lists authentication providers.
   *
   * @command odata:list-providers
   * @aliases aslp
   * @field-labels
   *   default: Default
   *   label: Label
   *   name: Name
   *   status: Token Status
   * @default-fields label,name,default,status
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The auth provider details.
   */
  public function listAuthProviders(): RowsOfFields {
    $rows = [];
    foreach ($this->authManager->getProviders() as $provider) {

      $rows[] = [
        'default' => $this->authManager->getConfig()->id() == $provider->id() ? '✓' : '',
        'label' => $provider->label(),
        'name' => $provider->id(),
        'status' => $provider->getPlugin()->hasAccessToken() ? 'Authorized' : 'Missing',
      ];
    }

    return new RowsOfFields($rows);
  }

}
