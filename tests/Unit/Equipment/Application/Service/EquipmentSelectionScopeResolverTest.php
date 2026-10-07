<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use Customer\Application\Port\Inbound\CustomerLookupPort;
use Equipment\Application\Port\Outbound\{EquipmentTypeCatalogPort, FacilityCustomerScopePort};
use Equipment\Application\Service\EquipmentSelectionScopeResolver;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

final class EquipmentSelectionScopeResolverTest extends TestCase
{
  #[Test]
  public function familyIncludesArchivedEquipmentForHistoricalParcReads(): void
  {
    $catalog = $this->createStub(EquipmentTypeCatalogPort::class);
    $catalog->method('list')->willReturn([
      new EquipmentTypeDefinition('fire_extinguisher', 'Extinguisher', 'fire'),
      new EquipmentTypeDefinition('legacy_fire', 'Legacy', 'fire', true),
      new EquipmentTypeDefinition('camera', 'Camera', 'safety'),
    ]);
    $resolver = new EquipmentSelectionScopeResolver($catalog, $this->createStub(CustomerLookupPort::class), $this->createStub(FacilityCustomerScopePort::class));
    self::assertSame(['fire_extinguisher', 'legacy_fire'], $resolver->typesForFamily('org', 'fire'));
    self::assertNull($resolver->typesForFamily('org', null));
    $this->expectException(InvalidValueException::class);
    $resolver->typesForFamily('org', 'building');
  }

  #[Test]
  public function unknownAndForeignCustomerAreHiddenBeforeFacilityLookup(): void
  {
    $customers = $this->createStub(CustomerLookupPort::class);
    $customers->method('find')->willReturn(null);
    $facilities = $this->createMock(FacilityCustomerScopePort::class);
    $facilities->expects(self::never())->method('findPublishedIdsForCustomer');
    $resolver = new EquipmentSelectionScopeResolver($this->createStub(EquipmentTypeCatalogPort::class), $customers, $facilities);
    $this->expectException(EquipmentNotFoundException::class);
    $resolver->customerFacilities('org', 'foreign', ['site']);
  }
}
