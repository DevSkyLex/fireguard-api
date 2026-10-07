<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Customer\Application\Port\Inbound\CustomerLookupPort;
use Equipment\Application\Port\Outbound\{EquipmentTypeCatalogPort, FacilityCustomerScopePort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Shared\Domain\Exception\InvalidValueException;

use function array_intersect;
use function array_values;
use function in_array;

/**
 * Resolves matching organization-owned scopes for list and count reads.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentSelectionScopeResolver
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EquipmentTypeCatalogPort $catalog, private CustomerLookupPort $customers, private FacilityCustomerScopePort $facilities)
  {
  }

  /**
   * @since 1.0.0
   *
   * @return ?list<string>
   */
  public function typesForFamily(string $organizationId, ?string $family): ?array
  {
    if (null === $family) {
      return null;
    }
    if (!in_array($family, ['fire', 'safety', 'other'], true)) {
      throw InvalidValueException::because('Invalid equipment family filter.');
    }
    $types = [];
    foreach ($this->catalog->list($organizationId) as $definition) {
      if ($definition->family === $family) {
        $types[] = $definition->value;
      }
    }

    return $types;
  }

  /**
   * @since 1.0.0
   *
   * @param ?list<string> $facilityIds an already selected subtree
   *
   * @return ?list<string>
   */
  public function customerFacilities(string $organizationId, ?string $customerId, ?array $facilityIds): ?array
  {
    if (null === $customerId) {
      return $facilityIds;
    }
    if (null === $this->customers->find($customerId, $organizationId)) {
      throw EquipmentNotFoundException::forCustomerScope($customerId);
    }
    $portfolio = $this->facilities->findPublishedIdsForCustomer($organizationId, $customerId);

    return null === $facilityIds ? $portfolio : array_values(array_intersect($facilityIds, $portfolio));
  }
}
