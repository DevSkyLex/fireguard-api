<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class SchemaContributionAdapter
 *
 * Owns Intervention constraints that scalar ORM mappings cannot declare.
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
      new ScalarForeignKeyDefinition('intervention_labels', 'FK_5F71203432C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('interventions', 'FK_5ADBAD7F32C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('intervention_recurrences', 'FK_298BE96032C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('intervention_templates', 'FK_20CA1CA732C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
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
      new PartialIndexDefinition('intervention_attachments', 'uniq_intervention_attachment_signature', ['intervention_id'], "((kind)::text = 'signature'::text)", true),
    ];
  }
  // #endregion
}
