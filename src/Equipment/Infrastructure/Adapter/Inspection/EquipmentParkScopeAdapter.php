<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Inspection;

use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilitySubtreeScopePort};
use Equipment\Application\Service\EquipmentSelectionScopeResolver;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;

/**
 * Resolves candidate equipment identities without exporting persistence records.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentParkScopeAdapter implements EquipmentParkScopePort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EquipmentRepositoryPort $equipment, private EquipmentSelectionScopeResolver $scopes, private FacilitySubtreeScopePort $facilities)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param list<string> $equipmentIds candidate identifiers
   * @param bool $includeDescendants whether an explicit facility includes published descendants
   *
   * @return list<string>
   */
  public function filterIds(string $organizationId, array $equipmentIds, ?string $family = null, ?string $customerId = null, ?string $facilityId = null, bool $includeDescendants = true): array
  {
    $facilityIds = null;
    if (null !== $facilityId) {
      $facilityIds = $this->facilities->findPublishedSubtreeIds($organizationId, $facilityId);
      if ([] === $facilityIds) {
        throw EquipmentNotFoundException::forFacilityScope($facilityId);
      }
      if (!$includeDescendants) {
        $facilityIds = [$facilityId];
      }
    }
    $criteria = new EquipmentListCriteria(
      facilityIds: $this->scopes->customerFacilities($organizationId, $customerId, $facilityIds),
      typeCodes: $this->scopes->typesForFamily($organizationId, $family),
    );

    return $this->equipment->findPublishedIdsMatching(EquipmentOrganizationId::fromString($organizationId), $criteria, $equipmentIds);
  }
}
