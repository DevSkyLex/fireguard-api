<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};

/**
 * Interface SchemaContributionPort
 *
 * Publishes module-owned schema invariants; declarations are pure and never create tables.
 *
 * @category Port
 */
interface SchemaContributionPort
{
  // #region Methods
  /**
   * Method foreignKeys
   *
   * Describes existing scalar keys confined to one entity manager's schema.
   *
   * @access public
   *
   * @return list<ScalarForeignKeyDefinition> the owner constraints
   */
  public function foreignKeys(): array;

  /**
   * Method partialIndexes
   *
   * Describes existing indexes with byte-exact PostgreSQL predicates.
   *
   * @access public
   *
   * @return list<PartialIndexDefinition> the owner indexes
   */
  public function partialIndexes(): array;
  // #endregion
}
