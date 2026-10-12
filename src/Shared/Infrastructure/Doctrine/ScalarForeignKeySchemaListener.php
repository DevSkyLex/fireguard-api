<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class ScalarForeignKeySchemaListener
 *
 * Applies loaded owner declarations without discovering or coupling to business tables.
 *
 * @category EventListener
 */
final readonly class ScalarForeignKeySchemaListener
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
   *
   * @param GenerateSchemaEventArgs $args the owning entity manager's schema
   *
   * @return void
   */
  public function postGenerateSchema(GenerateSchemaEventArgs $args): void
  {
    $schema = $args->getSchema();
    foreach ($this->contributions as $contribution) {
      foreach ($contribution->foreignKeys() as $key) {
        if (!$schema->hasTable($key->table) || !$schema->hasTable($key->foreignTable)) {
          continue;
        }

        foreach ($key->requiredTables as $requiredTable) {
          if (!$schema->hasTable($requiredTable)) {
            continue 2;
          }
        }

        $table = $schema->getTable($key->table);
        if ($table->hasForeignKey($key->name)) {
          continue;
        }

        $options = ['deferrable' => $key->deferrable];
        if (null !== $key->onDelete) {
          $options['onDelete'] = $key->onDelete;
        }

        $table->addForeignKeyConstraint($key->foreignTable, $key->columns, $key->foreignColumns, $options, $key->name);
      }
    }
  }
  // #endregion
}
