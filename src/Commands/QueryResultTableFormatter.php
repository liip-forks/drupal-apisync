<?php

declare(strict_types = 1);

namespace Drupal\apisync\Commands;

use Consolidation\OutputFormatters\Formatters\TableFormatter;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Format QueryResult metadata.
 */
class QueryResultTableFormatter extends TableFormatter {

  /**
   * {@inheritdoc}
   */
  public function validDataTypes(): array {
    return [
      new \ReflectionClass('\Drupal\apisync\Commands\QueryResult'),
    ];
  }

  /**
   * Given some metadata, decide how to display it.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Stream to write to.
   * @param \Drupal\apisync\Commands\QueryResult $query
   *   The query result.
   * @param \Consolidation\OutputFormatters\Options\FormatterOptions $options
   *   The options.
   */
  public function writeMetadata(OutputInterface $output, $query, FormatterOptions $options): void { // phpcs:ignore
    $output->writeln(str_pad(' ', 10 + strlen($query->getPrettyQuery()), '-'));
    $output->writeln(dt('  Size: !size', ['!size' => $query->getSize()]));
    $output->writeln(dt('  Total: !total', ['!total' => $query->getTotal()]));
    $output->writeln(dt('  Query: !query', ['!query' => $query->getPrettyQuery()]));
  }

}
