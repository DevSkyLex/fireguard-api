<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase OrganizationNonConformityResult.
 *
 * Row shape for the organization-wide non-conformity collection: the same
 * fields as {@see \Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\NonConformityResult}
 * plus the equipment the parent inspection was performed on, resolved once
 * per page rather than per row (the aggregate itself only knows its
 * inspection; equipment is the inspection's concern).
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationNonConformityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects a finding with its inspection and optional equipment identifiers and serial number.
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
   * @param ?string $equipmentId related equipment identifier, when available
   * @param ?string $equipmentSerialNumber related equipment serial number, when available
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
    public ?string $equipmentId,
    public ?string $equipmentSerialNumber,
  ) {
  }
  // #endregion
}
