<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class SchemaContributionAdapter
 *
 * Owns Messaging constraints that scalar ORM mappings cannot declare.
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
      new ScalarForeignKeyDefinition('messaging_conversations', 'FK_6489D332C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
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
    return [
      new PartialIndexDefinition('messaging_messages', 'idx_messaging_message_pinned', ['conversation_id', 'pinned_at'], '(pinned_at IS NOT NULL)', false),
    ];
  }
  // #endregion
}
