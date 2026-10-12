<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Schema;

/**
 * Class PartialIndexDefinition
 *
 * Describes an owner-declared schema invariant without exposing Doctrine types.
 *
 * @category Contract
 */
final readonly class PartialIndexDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Retains the existing database names and options during schema generation.
   *
   * @access public
   *
   * @param string $table the owner table
   * @param string $name the historical index name
   * @param list<string> $columns indexed columns in their existing order
   * @param string $predicate the exact PostgreSQL-normalized predicate
   * @param bool $unique whether this index enforces uniqueness
   *
   * @return void
   */
  public function __construct(
    public string $table,
    public string $name,
    public array $columns,
    public string $predicate,
    public bool $unique,
  ) {
  }
  // #endregion
}
