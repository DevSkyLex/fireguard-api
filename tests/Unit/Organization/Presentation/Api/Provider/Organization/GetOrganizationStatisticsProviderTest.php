<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics\{GetOrganizationStatisticsQuery, GetOrganizationStatisticsResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatisticsOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationStatisticsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(GetOrganizationStatisticsProvider::class)]
final class GetOrganizationStatisticsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationStatisticsProvider(
      queryBus: $queryBus,
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $output = $provider->provide(new Get(), []);

    self::assertNull($output);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441600', '550e8400-e29b-41d4-a716-446655441610', 'organization.read')
      ->willReturn(false);

    $provider = new GetOrganizationStatisticsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsLegacyAndNewFacilityCountFields(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationStatisticsQuery::class))
      ->willReturn(new GetOrganizationStatisticsResult(
        memberCount: 12,
        roleCount: 4,
        activeFacilityCount: 7,
        pendingInvitationCount: 2,
      ));

    $provider = new GetOrganizationStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(OrganizationStatisticsOutput::class, $output);
    self::assertSame(12, $output->memberCount);
    self::assertSame(4, $output->roleCount);
    self::assertSame(7, $output->facilityCount);
    self::assertSame(7, $output->activeFacilityCount);
    self::assertSame(2, $output->pendingInvitationCount);
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
