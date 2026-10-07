<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary;

use Customer\Application\Contract\CustomerSnapshot;
use Customer\Application\Port\Inbound\CustomerLookupPort;
use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, EquipmentTypeCatalogPort, FacilityCustomerScopePort};
use Equipment\Application\Service\EquipmentSelectionScopeResolver;
use Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary\{GetOrganizationEquipmentSummaryHandler, GetOrganizationEquipmentSummaryQuery};
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetOrganizationEquipmentSummaryHandlerTest extends TestCase
{
  #[Test]
  public function countUsesTheExactCatalogAndCustomerScopeBeforePagination(): void
  {
    $org = '960e8400-e29b-41d4-a716-446655470001';
    $catalog = $this->createStub(EquipmentTypeCatalogPort::class);
    $catalog->method('list')->willReturn([new EquipmentTypeDefinition('water_mist', 'Water mist', 'fire'), new EquipmentTypeDefinition('camera', 'Camera', 'safety')]);
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::once())->method('find')->with('customer', $org)->willReturn(new CustomerSnapshot('customer', 'Client', [], null));
    $facilities = $this->createMock(FacilityCustomerScopePort::class);
    $facilities->expects(self::once())->method('findPublishedIdsForCustomer')->with($org, 'customer')->willReturn(['site', 'room']);
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('countByStatusForCriteria')->with(new EquipmentOrganizationId($org), new EquipmentListCriteria(facilityIds: ['site', 'room'], typeCodes: ['water_mist']))
      ->willReturn(['operational' => 200, 'under_maintenance' => 2]);
    $handler = new GetOrganizationEquipmentSummaryHandler($repository, new EquipmentSelectionScopeResolver($catalog, $customers, $facilities));
    $result = $handler(new GetOrganizationEquipmentSummaryQuery($org, 'fire', 'customer'));
    self::assertSame('customer', $result->scope);
    self::assertSame(202, $result->totalItems);
    self::assertSame(['in_stock' => 0, 'operational' => 200, 'under_maintenance' => 2, 'decommissioned' => 0], $result->byStatus);
  }
}
