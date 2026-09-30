<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use Shared\Application\Message\CommandMessage;

/**
 * Class EditInspectionCommand
 *
 * Requests changes to selected inspection fields, using presence flags to distinguish omitted values.
 *
 * @category Command
 */
final readonly class EditInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries supplied inspection fields and presence flags so omitted PATCH values remain unchanged.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $inspectionId the inspection identifier
   * @param string|null $equipmentId the optional replacement equipment identifier
   * @param string|null $facilityId the optional replacement facility identifier
   * @param string|null $checklistId the optional replacement checklist identifier
   * @param string|null $result the optional inspection result
   * @param string|null $performedAt the optional inspection time
   * @param string|null $notes the optional inspection notes
   * @param string|null $signature the optional inspector signature
   * @param bool $hasEquipmentId whether equipmentId was supplied
   * @param bool $hasFacilityId whether facilityId was supplied
   * @param bool $hasChecklistId whether checklistId was supplied
   * @param bool $hasResult whether result was supplied
   * @param bool $hasPerformedAt whether performedAt was supplied
   * @param bool $hasNotes whether notes was supplied
   * @param bool $hasSignature whether signature was supplied
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $checklistId = null,
    public ?string $result = null,
    public ?string $performedAt = null,
    public ?string $notes = null,
    public ?string $signature = null,
    public bool $hasEquipmentId = false,
    public bool $hasFacilityId = false,
    public bool $hasChecklistId = false,
    public bool $hasResult = false,
    public bool $hasPerformedAt = false,
    public bool $hasNotes = false,
    public bool $hasSignature = false,
  ) {
  }
  // #endregion
}
