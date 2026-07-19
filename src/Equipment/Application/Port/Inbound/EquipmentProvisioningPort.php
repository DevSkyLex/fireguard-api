<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionEquipmentResult};

/**
 * Port EquipmentProvisioningPort.
 *
 * Inbound port other modules use to provision a single piece of equipment
 * programmatically (the Import module's bulk CSV import). The
 * implementation routes through the existing
 * `CreateEquipmentHandler` use case, so plan quota enforcement and every
 * domain invariant apply identically to the HTTP API — there is
 * intentionally no parallel creation path. Mirrors
 * `Intervention\Application\Port\Inbound\InterventionDraftFactoryPort`.
 *
 * Unlike the HTTP API, this port never throws another module's domain
 * exception: a quota breach or a validation failure is reported as a typed
 * {@see ProvisionEquipmentResult} outcome, so a caller processing many rows
 * (e.g. a CSV import) can continue past a single failed row.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentProvisioningPort
{
  // #region Methods
  /**
   * Method provision.
   *
   * Provisions one piece of equipment.
   *
   * @since 1.0.0
   *
   * @param ProvisionEquipmentRequest $request the provisioning request
   *
   * @return ProvisionEquipmentResult the provisioning outcome
   */
  public function provision(ProvisionEquipmentRequest $request): ProvisionEquipmentResult;
  // #endregion
}
