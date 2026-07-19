<?php

declare(strict_types=1);

namespace Import\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ImportRowValidationException.
 *
 * Raised by a row factory (`EquipmentRowFactory`, `FacilityRowFactory`) when a
 * CSV data row fails structural validation (a required column is missing or
 * blank, or a value cannot be coerced). Carries the offending column so
 * `ProcessImportJobHandler` can turn it into a non-fatal
 * {@see \Import\Domain\ValueObject\ImportRowError} entry in the job's error
 * report — this exception never fails the whole batch.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportRowValidationException extends RuntimeException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $message the failure reason
   * @param string $errorCode the machine-readable failure code (`invalid`|`missing_required`)
   * @param ?string $column the offending column name, when known
   */
  public function __construct(
    string $message,
    public readonly string $errorCode = 'invalid',
    public readonly ?string $column = null,
  ) {
    parent::__construct($message);
  }
  // #endregion

  // #region Methods
  /**
   * Method missingRequiredColumn.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $column the missing required column name
   *
   * @return self the exception instance
   */
  public static function missingRequiredColumn(string $column): self
  {
    return new self(
      sprintf('Column "%s" is required.', $column),
      errorCode: 'missing_required',
      column: $column,
    );
  }

  /**
   * Method invalidColumn.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $column the offending column name
   * @param string $reason the human-readable reason
   *
   * @return self the exception instance
   */
  public static function invalidColumn(string $column, string $reason): self
  {
    return new self(
      sprintf('Column "%s" is invalid: %s', $column, $reason),
      errorCode: 'invalid',
      column: $column,
    );
  }
  // #endregion
}
