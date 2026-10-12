<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Schema;

/**
 * Class ScalarForeignKeyDefinition
 *
 * Describes an owner-declared schema invariant without exposing Doctrine types.
 *
 * @category Contract
 */
final readonly class ScalarForeignKeyDefinition
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
   * @param string $name the historical constraint name
   * @param list<string> $columns scalar source columns
   * @param string $foreignTable the target in the same schema
   * @param list<string> $foreignColumns target columns
   * @param string|null $onDelete the existing deletion action, null for NO ACTION
   * @param bool $deferrable the historical constraint option
   * @param list<string> $requiredTables additional tables required by this invariant
   *
   * @return void
   */
  public function __construct(
    public string $table,
    public string $name,
    public array $columns,
    public string $foreignTable,
    public array $foreignColumns,
    public ?string $onDelete = null,
    public bool $deferrable = false,
    public array $requiredTables = [],
  ) {
  }
  // #endregion
}
