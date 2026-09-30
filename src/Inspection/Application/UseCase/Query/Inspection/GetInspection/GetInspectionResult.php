<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetInspection;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInspectionResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects inspection identity, inspected assets, outcome, inspector, checklist, findings, and lifecycle timestamps.
   *
   * @access public
   *
   * @param string $inspectionId identifier of the inspection
   * @param string $organizationId organization owning the inspection
   * @param string $equipmentId inspected equipment identifier
   * @param ?string $facilityId facility associated with the inspection, when present
   * @param string $result inspection outcome
   * @param string $status inspection lifecycle status
   * @param string $performedAt timestamp when the inspection was performed
   * @param string $inspectorType type of inspector recorded for the inspection
   * @param string $inspectorName display name of the inspector
   * @param ?string $inspectorUserId inspector user identifier, when linked
   * @param ?string $inspectorOrganizationName inspector organization name, when applicable
   * @param ?string $checklistId checklist used for the inspection, when present
   * @param ?string $notes inspection notes, when recorded
   * @param ?string $signature inspector signature data, when recorded
   * @param int $nonConformitiesCount number of findings associated with the inspection
   * @param DateTimeImmutable $createdAt time the inspection record was created
   * @param DateTimeImmutable $updatedAt time the inspection record was last updated
   * @param ?string $equipmentSerialNumber equipment serial number, when available
   * @param ?string $facilityName facility display name, when available
   * @param ?string $checklistName checklist display name, when available
   *
   * @return void
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public string $result,
    public string $status,
    public string $performedAt,
    public string $inspectorType,
    public string $inspectorName,
    public ?string $inspectorUserId,
    public ?string $inspectorOrganizationName,
    public ?string $checklistId,
    public ?string $notes,
    public ?string $signature,
    public int $nonConformitiesCount,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $equipmentSerialNumber = null,
    public ?string $facilityName = null,
    public ?string $checklistName = null,
  ) {
  }
  // #endregion
}
