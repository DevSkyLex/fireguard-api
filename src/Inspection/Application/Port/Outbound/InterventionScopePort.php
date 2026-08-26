<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

/**
 * Port InterventionScopePort.
 *
 * The two things this module needs from an intervention when it ties a
 * canonical response to one: who owns it, and the revision bump that tells
 * a field client the preparation moved.
 *
 * Declared here, implemented in the Intervention module — the same shape as
 * {@see FacilityValidationPort} and {@see EquipmentValidationPort}, and the
 * reason `InspectionResponseProcessor` no longer imports
 * `Intervention\…\Record\InterventionRecord`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionScopePort
{
  // #region Methods
  /**
   * Method organizationIdOf.
   *
   * Resolves the organization an intervention belongs to.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention identifier
   *
   * @return ?string the owning organization identifier, or null when the intervention does not exist
   */
  public function organizationIdOf(string $interventionId): ?string;

  /**
   * Method touchDraft.
   *
   * Bumps the revision of an intervention that is still open to change, so
   * a field client polling `If-Match` sees the preparation move.
   *
   * A null identifier, an absent intervention and one already past
   * submission are all no-ops — the caller does not have to know which.
   *
   * @since 1.0.0
   *
   * @param ?string $interventionId the intervention identifier
   */
  public function touchDraft(?string $interventionId): void;
  // #endregion
}
