<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port InterventionScopePort.
 *
 * The revision bump this module needs when it mutates a row an intervention
 * is still preparing: a field client polling `If-Match` has to see the
 * preparation move.
 *
 * Declared here, implemented in the Intervention module. Deliberately a
 * SECOND declaration of the same capability the Inspection module declares —
 * this repository's convention is that each consumer owns its port, exactly
 * as `Equipment\…\FacilityValidationPort` and `Inspection\…\FacilityValidationPort`
 * coexist. Sharing one interface across modules would make either consumer's
 * change a breaking change for the other.
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
   * Method touchDraft.
   *
   * Bumps the revision of an intervention that is still open to change.
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
