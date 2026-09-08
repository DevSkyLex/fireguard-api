<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Inspection;

use DateTimeImmutable;

/**
 * Contract OpenNonConformitySummary.
 *
 * Read model exposed outside the Inspection module: one unresolved
 * non-conformity line of the organization's weekly digest, without depending
 * on the Inspection module's Domain or Infrastructure layers.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OpenNonConformitySummary
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the non-conformity identifier
   * @param string $inspectionId the owning inspection identifier
   * @param string $description the non-conformity description
   * @param string $severity the severity value
   * @param string $status the status value
   * @param ?DateTimeImmutable $dueAt the resolution due datetime, when set
   * @param DateTimeImmutable $createdAt the instant the non-conformity was opened
   */
  public function __construct(
    public string $id,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?DateTimeImmutable $dueAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
