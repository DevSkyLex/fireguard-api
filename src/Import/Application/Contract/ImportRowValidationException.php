<?php

declare(strict_types=1);

namespace Import\Application\Contract;

use RuntimeException;

use function sprintf;

/**
 * Exception ImportRowValidationException.
 *
 * Published structural row rejection raised by owner factories before provisioning.
 * The engine translates its safe code, message and column into a confirmed failure;
 * technical failures must propagate separately for transaction rollback and retry.
 *
 * @category Exception
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
