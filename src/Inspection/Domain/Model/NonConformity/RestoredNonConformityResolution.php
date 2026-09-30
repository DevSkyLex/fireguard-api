<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\ValueObject\NonConformityStatus;

/** Persisted resolution state, including dates that may be absent independently. */
final readonly class RestoredNonConformityResolution
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores finding status and independently optional due date, resolution time, and notes.
   *
   * @access public
   *
   * @param NonConformityStatus $status lifecycle status restored for the finding
   * @param ?DateTimeImmutable $dueAt resolution deadline, when persisted
   * @param ?DateTimeImmutable $resolvedAt resolution time, when persisted
   * @param ?string $notes resolution notes, when persisted
   *
   * @return void
   */
  public function __construct(
    public NonConformityStatus $status,
    public ?DateTimeImmutable $dueAt = null,
    public ?DateTimeImmutable $resolvedAt = null,
    public ?string $notes = null,
  ) {
  }
  // #endregion
}
