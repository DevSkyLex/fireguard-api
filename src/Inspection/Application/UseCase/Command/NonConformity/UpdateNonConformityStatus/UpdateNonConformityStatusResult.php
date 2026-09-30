<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateNonConformityStatusResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNonConformityStatusResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the updated non-conformity details and lifecycle timestamps.
   *
   * @access public
   *
   * @param string $nonConformityId identifier of the updated finding
   * @param string $inspectionId inspection containing the finding
   * @param string $description recorded deficiency description
   * @param string $severity recorded severity
   * @param string $status lifecycle status after the transition
   * @param ?string $dueAt resolution deadline, when set
   * @param ?string $resolvedAt resolution timestamp, when resolved
   * @param ?string $notes handling notes, when set
   * @param DateTimeImmutable $createdAt time the finding was created
   * @param DateTimeImmutable $updatedAt time the finding was last updated
   *
   * @return void
   */
  public function __construct(
    public string $nonConformityId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?string $dueAt,
    public ?string $resolvedAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
