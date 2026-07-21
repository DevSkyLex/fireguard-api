<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

/**
 * Port EquipmentNamingPort.
 *
 * Resolves equipment identifiers into the serial number printed on the device,
 * so an inspection can name what it inspected.
 *
 * Separate from {@see EquipmentValidationPort}: that one answers "may an
 * inspection be recorded against this equipment" and throws. Naming is a
 * read-side lookup that runs on every listing and must never reject anything.
 *
 * Returns the identifier, not a rendered label: how an equipment is titled
 * ("Extinguisher / Water") is presentation the frontend owns and localises,
 * while a serial number is what is written on the device itself.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentNamingPort
{
  // #region Methods
  /**
   * Method findSerialNumbersByIds.
   *
   * Resolves the serial number of each given equipment, in one round trip.
   *
   * Equipment with no recorded serial number, and identifiers that cannot be
   * resolved, are absent from the result rather than mapped to an empty
   * string — an unknown serial is not a blank one.
   *
   * @since 1.0.0
   *
   * @param list<string> $equipmentIds the equipment identifiers to resolve
   *
   * @return array<string, string> serial number keyed by equipment identifier
   */
  public function findSerialNumbersByIds(array $equipmentIds): array;
  // #endregion
}
