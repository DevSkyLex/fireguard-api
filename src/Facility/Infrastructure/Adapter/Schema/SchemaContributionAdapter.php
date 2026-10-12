<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Schema;

use Shared\Application\Contract\Schema\{PartialIndexDefinition, ScalarForeignKeyDefinition};
use Shared\Application\Port\Outbound\Schema\SchemaContributionPort;

/**
 * Class SchemaContributionAdapter
 *
 * Owns Facility constraints that scalar ORM mappings cannot declare.
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
      new ScalarForeignKeyDefinition('facility_metadata_fields', 'FK_37E6724C32C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
      new ScalarForeignKeyDefinition('facilities', 'FK_ADE885D532C8A3DE', ['organization_id'], 'organizations', ['id'], 'CASCADE'),
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
      new PartialIndexDefinition('facility_attachments', 'uniq_facility_attachment_primary_plan', ['facility_id'], 'is_primary_plan', true),
    ];
  }
  // #endregion
}
