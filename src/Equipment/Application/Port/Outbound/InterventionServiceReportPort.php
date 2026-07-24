<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Intervention\InterventionServiceReport;

/**
 * Port InterventionServiceReportPort.
 *
 * Reads back the set of published equipment changes carried by an
 * intervention, so the Equipment module can synthesize regulatory service
 * history entries once the intervention publishes. Consumed by
 * {@see \Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory\RecordInterventionServiceHistoryHandler};
 * hosted by the Intervention module
 * ({@see \Intervention\Infrastructure\Adapter\Equipment\InterventionServiceReportAdapter}),
 * mirroring `Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionServiceReportPort
{
  // #region Methods
  /**
   * Method serviceReport.
   *
   * Returns the equipment service report for a published intervention, or
   * null when the intervention is not found. All applied equipment changes
   * ever recorded for the intervention are returned (not scoped to a single
   * publication): each change is applied exactly once and dedup on the
   * change identifier makes re-reading them across multiple publications
   * harmless.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention identifier
   *
   * @return ?InterventionServiceReport the service report, when the intervention is found
   */
  public function serviceReport(string $interventionId): ?InterventionServiceReport;
  // #endregion
}
