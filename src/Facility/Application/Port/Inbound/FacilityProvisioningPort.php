<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionFacilityResult};

/**
 * Port FacilityProvisioningPort.
 *
 * Inbound port other modules use to provision a single facility
 * programmatically (the Import module's bulk CSV import). The
 * implementation routes through the existing `CreateFacilityHandler` use
 * case, so plan quota enforcement and every domain invariant apply
 * identically to the HTTP API — there is intentionally no parallel creation
 * path. Mirrors `Intervention\Application\Port\Inbound\InterventionDraftFactoryPort`
 * and `Equipment\Application\Port\Inbound\EquipmentProvisioningPort`.
 *
 * Unlike the HTTP API, this port never throws another module's domain
 * exception: a quota breach, an unknown parent code, or a validation
 * failure is reported as a typed {@see ProvisionFacilityResult} outcome, so
 * a caller processing many rows (e.g. a CSV import) can continue past a
 * single failed row.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityProvisioningPort
{
  // #region Methods
  /**
   * Method provision.
   *
   * Provisions one facility.
   *
   * @since 1.0.0
   *
   * @param ProvisionFacilityRequest $request the provisioning request
   *
   * @return ProvisionFacilityResult the provisioning outcome
   */
  public function provision(ProvisionFacilityRequest $request): ProvisionFacilityResult;

  /**
   * Method restoreSimulation.
   *
   * Reconstructs an already confirmed simulation row without quota checks,
   * creation dispatch, or persistence. Unavailable parents remain invalid.
   *
   * @since 1.0.0
   *
   * @param ProvisionFacilityRequest $request the original row with its stable identifier and preceding graph
   *
   * @return ProvisionFacilityResult the restored node or a typed unavailable-parent outcome
   */
  public function restoreSimulation(ProvisionFacilityRequest $request): ProvisionFacilityResult;
  // #endregion
}
