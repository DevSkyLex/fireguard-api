<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

/**
 * ValueObject ImportRowError.
 *
 * One reported row outcome inside an {@see \Import\Domain\Model\ImportJob\ImportJob}'s
 * report: a row that failed structural validation, an invalid reference
 * (e.g. an unknown facility parent code), or would exceed the
 * organization's plan quota. Row failures are non-fatal to the batch — the
 * job still reaches `completed` with a partial success.
 *
 * For a **dry-run** job the same list also carries one entry per
 * successfully validated row, coded `would_create` — {@see
 * \Import\Domain\Model\ImportJob\ImportJob::recordRowSuccess()} accepts an
 * optional entry for exactly this purpose, so the dry-run report reuses this
 * field rather than a second, parallel structure. A real (write) run never
 * appends a `would_create` entry: its report stays failures-only.
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
   * @param string $code the machine-readable outcome code (`quota_exceeded`|`invalid`|`missing_required`|`would_create`)
   * @param string $message the human-readable reason
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
