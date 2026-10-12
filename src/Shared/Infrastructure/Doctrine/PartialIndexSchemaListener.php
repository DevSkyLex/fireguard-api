<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class PartialIndexSchemaListener
 *
 * Applies loaded owner declarations without discovering or coupling to business tables.
 *
 * @category EventListener
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PartialIndexSchemaListener
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Accepts only the contributors included by the application composition.
   *
   * @access public
   *
   * @param iterable<SchemaContributionPort> $contributions loaded module declarations
   *
   * @return void
   */
  public function __construct(private iterable $contributions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method postGenerateSchema
   *
   * Preserves existing invariants only for tables in this entity manager's schema.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GenerateSchemaEventArgs $args the owning entity manager's schema
   *
   * @return void
   */
  public function postGenerateSchema(GenerateSchemaEventArgs $args): void
  {
    $schema = $args->getSchema();
    foreach ($this->contributions as $contribution) {
      foreach ($contribution->partialIndexes() as $index) {
        if (!$schema->hasTable($index->table)) {
          continue;
        }

        $table = $schema->getTable($index->table);
        if ($table->hasIndex($index->name)) {
          continue;
        }

        $options = ['where' => $index->predicate];
        if ($index->unique) {
          $table->addUniqueIndex($index->columns, $index->name, $options);
        } else {
          $table->addIndex($index->columns, $index->name, [], $options);
        }
      }
    }
  }
  // #endregion
}
