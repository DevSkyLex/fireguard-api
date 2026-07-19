<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

/**
 * ValueObject ImportRowError.
 *
 * One reported row failure inside an {@see \Import\Domain\Model\ImportJob\ImportJob}'s
 * error report: a row that failed structural validation, an invalid
 * reference (e.g. an unknown facility parent code), or would exceed the
 * organization's plan quota. Row failures are non-fatal to the batch — the
 * job still reaches `completed` with a partial success.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportRowError
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $rowNumber the 1-based data row number (header excluded)
   * @param string $code the machine-readable failure code (`quota_exceeded`|`invalid`|`missing_required`)
   * @param string $message the human-readable failure reason
   * @param ?string $column the offending column name, when known
   */
  public function __construct(
    public int $rowNumber,
    public string $code,
    public string $message,
    public ?string $column = null,
  ) {
  }
  // #endregion
}
