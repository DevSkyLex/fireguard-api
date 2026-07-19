<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Intervention;

/**
 * Contract InterventionServiceReport.
 *
 * Read model exposed by {@see \Equipment\Application\Port\Outbound\InterventionServiceReportPort},
 * summarizing a published intervention's equipment changes so the Equipment
 * module can synthesize service history entries, without depending on the
 * Intervention module's Domain or Infrastructure layers.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionServiceReport
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $number the intervention's human-readable per-organization number
   * @param ?string $actorId the intervention's responsible member identifier, when set
   * @param list<ServicedEquipmentEntry> $equipment the serviced equipment entries
   */
  public function __construct(
    public int $number,
    public ?string $actorId,
    public array $equipment,
  ) {
  }
  // #endregion
}
