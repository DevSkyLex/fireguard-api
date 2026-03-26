<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics\{GetOrganizationEquipmentStatisticsHandler, GetOrganizationEquipmentStatisticsQuery, GetOrganizationEquipmentStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationEquipmentStatisticsHandler::class)]
final class GetOrganizationEquipmentStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  #[Test]
  public function testInvokeReturnsDetailedEquipmentStatistics(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.equipment.read')
      ->willReturn(true);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var EquipmentStatisticsPort&MockObject $equipmentStatistics */
    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::once())->method('countEquipment')->with(self::ORG_ID)->willReturn(17);
    $equipmentStatistics->expects(self::once())->method('countEquipmentByStatus')->with(self::ORG_ID)->willReturn([
      'in_stock' => 3,
      'operational' => 9,
      'under_maintenance' => 4,
      'decommissioned' => 1,
    ]);
    $equipmentStatistics->expects(self::once())->method('countEquipmentByType')->with(self::ORG_ID)->willReturn([
      'fire_extinguisher' => 6,
      'smoke_detector' => 5,
      'sprinkler' => 2,
      'camera' => 3,
      'other' => 1,
    ]);

    $handler = new GetOrganizationEquipmentStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      equipmentStatistics: $equipmentStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationEquipmentStatisticsQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationEquipmentStatisticsResult::class, $result);
    self::assertSame(17, $result->totalCount);
    self::assertSame(3, $result->inStockCount);
    self::assertSame(9, $result->operationalCount);
    self::assertSame(4, $result->underMaintenanceCount);
    self::assertSame(1, $result->decommissionedCount);
    self::assertSame(6, $result->countsByType['fire_extinguisher']);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var EquipmentStatisticsPort&MockObject $equipmentStatistics */
    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::never())->method('countEquipment');

    $handler = new GetOrganizationEquipmentStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      equipmentStatistics: $equipmentStatistics,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationEquipmentStatisticsQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.equipment.read')
      ->willReturn(false);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var EquipmentStatisticsPort&MockObject $equipmentStatistics */
    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::never())->method('countEquipment');

    $handler = new GetOrganizationEquipmentStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      equipmentStatistics: $equipmentStatistics,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationEquipmentStatisticsQuery(self::ORG_ID, self::USER_ID));
  }
}
