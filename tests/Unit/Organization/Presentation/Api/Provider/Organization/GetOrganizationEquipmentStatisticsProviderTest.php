<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics\{GetOrganizationEquipmentStatisticsQuery, GetOrganizationEquipmentStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationEquipmentStatisticsOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationEquipmentStatisticsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(GetOrganizationEquipmentStatisticsProvider::class)]
final class GetOrganizationEquipmentStatisticsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441900'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationEquipmentStatisticsProvider(
      queryBus: $queryBus,
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441900'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441900', '550e8400-e29b-41d4-a716-446655441910', 'organization.equipment.read')
      ->willReturn(false);

    $provider = new GetOrganizationEquipmentStatisticsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441910']);
  }

  #[Test]
  public function testProvideMapsDetailedEquipmentStatistics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441900'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationEquipmentStatisticsQuery::class))
      ->willReturn(new GetOrganizationEquipmentStatisticsResult(
        totalCount: 17,
        inStockCount: 3,
        operationalCount: 9,
        underMaintenanceCount: 4,
        decommissionedCount: 1,
        countsByType: [
          'fire_extinguisher' => 6,
          'smoke_detector' => 5,
          'sprinkler' => 2,
          'camera' => 3,
          'other' => 1,
        ],
      ));

    $provider = new GetOrganizationEquipmentStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441910']);

    self::assertInstanceOf(OrganizationEquipmentStatisticsOutput::class, $output);
    self::assertSame(17, $output->totalCount);
    self::assertSame(3, $output->inStockCount);
    self::assertSame(9, $output->operationalCount);
    self::assertSame(4, $output->underMaintenanceCount);
    self::assertSame(1, $output->decommissionedCount);
    self::assertSame(6, $output->countsByType['fire_extinguisher']);
  }

  #[Test]
  public function testProvideMapsOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441900'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationEquipmentStatisticsQuery::class))
      ->willThrowException(OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441910'));

    $provider = new GetOrganizationEquipmentStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441910']);
  }

  #[Test]
  public function testProvideMapsWrappedPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441900'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationEquipmentStatisticsQuery('550e8400-e29b-41d4-a716-446655441910', '550e8400-e29b-41d4-a716-446655441900')),
      [OrganizationAccessDeniedException::missingPermission('organization.equipment.read')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationEquipmentStatisticsQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationEquipmentStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441910']);
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
