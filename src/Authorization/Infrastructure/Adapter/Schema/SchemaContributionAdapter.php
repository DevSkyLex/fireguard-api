<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class SchemaContributionAdapter
 *
 * Owns Authorization constraints that scalar ORM mappings cannot declare.
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
      new ScalarForeignKeyDefinition('user_roles', 'FK_54FCD59FA76ED395', ['user_id'], 'users', ['id'], requiredTables: ['roles']),
      new ScalarForeignKeyDefinition('user_roles', 'FK_54FCD59FD60322AC', ['role_id'], 'roles', ['id'], requiredTables: ['users']),
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
