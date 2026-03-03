<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\{ListEquipmentsQuery, ListEquipmentsResult};
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentsProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

#[CoversClass(ListEquipmentsProvider::class)]
final class ListEquipmentsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyListWhenNoEquipments(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441500';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441501');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListEquipmentsQuery::class))
      ->willReturn(new ListEquipmentsResult(equipments: []));

    $requestStack = $this->buildRequestStack();

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertCount(0, $output);
  }

  #[Test]
  public function testProvideReturnsEquipmentOutputList(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441510';
    $equipmentId1 = '550e8400-e29b-41d4-a716-446655441511';
    $equipmentId2 = '550e8400-e29b-41d4-a716-446655441512';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441513');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    $equipmentResult1 = new GetEquipmentResult(
      equipmentId: $equipmentId1,
      organizationId: $organizationId,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: 'Sicli',
      model: null,
      serialNumber: 'EXT-001',
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );

    $equipmentResult2 = new GetEquipmentResult(
      equipmentId: $equipmentId2,
      organizationId: $organizationId,
      facilityId: null,
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListEquipmentsResult(equipments: [$equipmentResult1, $equipmentResult2]));

    $requestStack = $this->buildRequestStack();

    $provider = new ListEquipmentsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertCount(2, $output);
    self::assertSame($equipmentId1, $output[0]->id);
    self::assertSame('fire_extinguisher', $output[0]->type);
    self::assertSame($equipmentId2, $output[1]->id);
    self::assertSame('smoke_detector', $output[1]->type);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function buildRequestStack(): RequestStack
  {
    $request = Request::create('/api/organizations/org-id/equipment', 'GET');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    return $requestStack;
  }
}
