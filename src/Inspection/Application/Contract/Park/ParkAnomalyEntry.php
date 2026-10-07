<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Park;

use DateTimeImmutable;

/**
 * Contract ParkAnomalyEntry.
 *
 * An Inspection-owned unresolved finding and its equipment identity, without persistence objects.
 *
 * @category Contract
 */
final readonly class ParkAnomalyEntry
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $id finding identifier
   * @param string $inspectionId published parent inspection
   * @param string $equipmentId equipment inspected
   * @param string $description recorded defect
   * @param string $severity recorded severity
   * @param string $status open or in_progress
   * @param DateTimeImmutable|null $dueAt optional resolution deadline
   * @param DateTimeImmutable|null $resolvedAt optional previous resolution timestamp
   * @param string|null $notes handling notes
   * @param DateTimeImmutable $createdAt recorded creation instant
   * @param DateTimeImmutable $updatedAt recorded update instant
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $inspectionId,
    public string $equipmentId,
    public string $description,
    public string $severity,
    public string $status,
    public ?DateTimeImmutable $dueAt,
    public ?DateTimeImmutable $resolvedAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
