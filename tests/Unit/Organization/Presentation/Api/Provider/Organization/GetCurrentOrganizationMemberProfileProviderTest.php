<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile\{
  GetCurrentOrganizationMemberProfileQuery,
  GetCurrentOrganizationMemberProfileResult,
  GetCurrentOrganizationMemberProfileRoleResult
};
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\CurrentOrganizationMemberProfileOutput;
use Organization\Presentation\Api\Provider\Organization\GetCurrentOrganizationMemberProfileProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

#[CoversClass(GetCurrentOrganizationMemberProfileProvider::class)]
final class GetCurrentOrganizationMemberProfileProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442301'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetCurrentOrganizationMemberProfileProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), []);

    self::assertNull($output);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new GetCurrentOrganizationMemberProfileProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442302']);
  }

  #[Test]
  public function testProvideMapsCurrentOrganizationMemberProfile(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $roleCreatedAt = new DateTimeImmutable('2026-01-20T10:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442303'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetCurrentOrganizationMemberProfileQuery $query): bool => '550e8400-e29b-41d4-a716-446655442304' === $query->organizationId && '550e8400-e29b-41d4-a716-446655442303' === $query->userId))
      ->willReturn(new GetCurrentOrganizationMemberProfileResult(
        id: '550e8400-e29b-41d4-a716-446655442305',
        organizationId: '550e8400-e29b-41d4-a716-446655442304',
        userId: '550e8400-e29b-41d4-a716-446655442303',
        isActive: true,
        joinedAt: $joinedAt,
        roles: [
          new GetCurrentOrganizationMemberProfileRoleResult(
            id: '550e8400-e29b-41d4-a716-446655442306',
            organizationId: '550e8400-e29b-41d4-a716-446655442304',
            name: 'manager',
            permissions: ['organization.read', 'organization.members.read'],
            isSystem: false,
            createdAt: $roleCreatedAt,
            description: 'Manager role',
            memberCount: 7,
          ),
        ],
        permissions: ['organization.read', 'organization.members.read'],
      ));

    $provider = new GetCurrentOrganizationMemberProfileProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442304']);

    self::assertInstanceOf(CurrentOrganizationMemberProfileOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655442305', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655442304', $output->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655442303', $output->userId);
    self::assertCount(1, $output->roles);
    self::assertSame('manager', $output->roles[0]->name);
    self::assertSame(7, $output->roles[0]->memberCount);
    self::assertCount(2, $output->permissions);
    self::assertSame('organization.read', $output->permissions[0]->name);
  }

  #[Test]
  public function testProvideMapsMissingMembershipToNotFound(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442307'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(OrganizationMemberNotFoundException::forUserInOrganization(
        '550e8400-e29b-41d4-a716-446655442307',
        '550e8400-e29b-41d4-a716-446655442308',
      ));

    $provider = new GetCurrentOrganizationMemberProfileProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442308']);
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
