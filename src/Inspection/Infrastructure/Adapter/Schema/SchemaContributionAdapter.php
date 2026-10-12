<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class SchemaContributionAdapter
 *
 * Owns Inspection constraints that scalar ORM mappings cannot declare.
 *
 * @category Adapter
 */
final readonly class SchemaContributionAdapter implements SchemaContributionPort
{
  // #region Methods
  /**
   * Method foreignKeys
   *
   * Keeps the existing constraint names, deletion actions and database isolation.
   *
   * @access public
   *
   * @return list<ScalarForeignKeyDefinition> the owner scalar constraints
   */
  public function foreignKeys(): array
  {
    return [
      new ScalarForeignKeyDefinition('checklists', 'FK_B0839B3F32C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('inspections', 'FK_8625499032C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('inspection_responses', 'FK_CD1506CC32C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
    ];
  }

  /**
   * Method partialIndexes
   *
   * Keeps existing uniqueness and PostgreSQL-normalized predicates unchanged.
   *
   * @access public
   *
   * @return list<PartialIndexDefinition> the owner partial indexes
   */
  public function partialIndexes(): array
  {
    return [];
  }
  // #endregion
}
