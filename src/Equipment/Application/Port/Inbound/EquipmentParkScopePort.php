<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

/**
 * Filters candidate identifiers against the same published parc scope as collection reads.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentParkScopePort
{
  /**
   * @since 1.0.0
   *
   * @param list<string> $equipmentIds candidate identifiers
   * @param bool $includeDescendants whether an explicit facility includes published descendants; defaults to the legacy subtree scope
   *
   * @return list<string>
   */
  public function filterIds(string $organizationId, array $equipmentIds, ?string $family = null, ?string $customerId = null, ?string $facilityId = null, bool $includeDescendants = true): array;
}
