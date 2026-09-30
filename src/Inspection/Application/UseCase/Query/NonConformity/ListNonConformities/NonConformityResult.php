<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase NonConformityResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects a finding description, severity, lifecycle, resolution data, and timestamps.
   *
   * @access public
   *
   * @param string $nonConformityId identifier of the finding
   * @param string $inspectionId inspection containing the finding
   * @param string $description recorded deficiency description
   * @param string $severity recorded severity
   * @param string $status current lifecycle status
   * @param ?string $dueAt resolution deadline, when set
   * @param ?string $resolvedAt resolution timestamp, when resolved
   * @param ?string $notes handling notes, when present
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
