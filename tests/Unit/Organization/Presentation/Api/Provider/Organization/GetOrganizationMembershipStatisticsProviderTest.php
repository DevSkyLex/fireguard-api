<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics\{GetOrganizationMembershipStatisticsQuery, GetOrganizationMembershipStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMembershipStatisticsOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationMembershipStatisticsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(GetOrganizationMembershipStatisticsProvider::class)]
final class GetOrganizationMembershipStatisticsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationMembershipStatisticsProvider(
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
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441800', '550e8400-e29b-41d4-a716-446655441810', 'organization.members.read')
      ->willReturn(false);

    $provider = new GetOrganizationMembershipStatisticsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsDetailedMembershipStatistics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationMembershipStatisticsQuery::class))
      ->willReturn(new GetOrganizationMembershipStatisticsResult(
        memberCount: 14,
        activeMemberCount: 12,
        inactiveMemberCount: 2,
        roleCount: 5,
        systemRoleCount: 2,
        customRoleCount: 3,
        invitationCount: 7,
        pendingInvitationCount: 4,
        acceptedInvitationCount: 2,
        revokedInvitationCount: 1,
        expiredInvitationCount: 0,
      ));

    $provider = new GetOrganizationMembershipStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);

    self::assertInstanceOf(OrganizationMembershipStatisticsOutput::class, $output);
    self::assertSame(14, $output->memberCount);
    self::assertSame(12, $output->activeMemberCount);
    self::assertSame(2, $output->inactiveMemberCount);
    self::assertSame(5, $output->roleCount);
    self::assertSame(2, $output->systemRoleCount);
    self::assertSame(3, $output->customRoleCount);
    self::assertSame(7, $output->invitationCount);
    self::assertSame(4, $output->pendingInvitationCount);
    self::assertSame(2, $output->acceptedInvitationCount);
    self::assertSame(1, $output->revokedInvitationCount);
    self::assertSame(0, $output->expiredInvitationCount);
  }

  #[Test]
  public function testProvideMapsApplicationPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationMembershipStatisticsQuery::class))
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.roles.read'));

    $provider = new GetOrganizationMembershipStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationMembershipStatisticsQuery::class))
      ->willThrowException(OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441810'));

    $provider = new GetOrganizationMembershipStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsWrappedPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationMembershipStatisticsQuery('550e8400-e29b-41d4-a716-446655441810', '550e8400-e29b-41d4-a716-446655441800')),
      [OrganizationAccessDeniedException::missingPermission('organization.roles.read')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationMembershipStatisticsQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationMembershipStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
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
