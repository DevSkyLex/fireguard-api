<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use Equipment\Presentation\Api\Provider\Equipment\GetEquipmentProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(GetEquipmentProvider::class)]
final class GetEquipmentProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsWrappedNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441300';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441301';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441302');

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

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new GetEquipmentQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      )),
      exceptions: [EquipmentNotFoundException::withId($equipmentId)],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetEquipmentProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441310';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441311';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441312');

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

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetEquipmentQuery::class))
      ->willReturn(new GetEquipmentResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: null,
        type: 'fire_extinguisher',
        subType: 'CO2 6kg',
        brand: 'Sicli',
        model: 'Pro 6',
        serialNumber: 'EXT-2026-001',
        locationLabel: 'Building A - Floor 1',
        status: 'operational',
        installedAt: '2026-01-01T00:00:00+00:00',
        commissionedAt: '2026-01-02T00:00:00+00:00',
        tags: [['id' => '550e8400-e29b-41d4-a716-446655441400', 'name' => 'urgent', 'organizationId' => $organizationId]],
        createdAt: $now,
        updatedAt: $now,
        maintenanceDueStatus: 'due_soon',
      ));

    $provider = new GetEquipmentProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($equipmentId, $output->id);
    self::assertSame('fire_extinguisher', $output->type);
    self::assertSame('Sicli', $output->brand);
    self::assertSame('EXT-2026-001', $output->serialNumber);
    self::assertSame('operational', $output->status);
    self::assertCount(1, $output->tags);
    self::assertInstanceOf(TagOutput::class, $output->tags[0]);
    self::assertSame('urgent', $output->tags[0]->name);
    self::assertSame($organizationId, $output->tags[0]->organizationId);
    self::assertSame('due_soon', $output->maintenanceDueStatus);
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
}
