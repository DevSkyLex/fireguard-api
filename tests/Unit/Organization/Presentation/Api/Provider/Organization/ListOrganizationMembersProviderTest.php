<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{GetOrganizationMemberResult, ListOrganizationMembersQuery, ListOrganizationMembersResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationMembersProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListOrganizationMembersProvider::class)]
final class ListOrganizationMembersProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), []);

    self::assertSame([], $output);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441700', '550e8400-e29b-41d4-a716-446655441710', 'organization.members.read')
      ->willReturn(false);

    $provider = new ListOrganizationMembersProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441710']);
  }

  #[Test]
  public function testProvideMapsMembersResult(): void
  {
    $joinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441700'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListOrganizationMembersQuery::class))
      ->willReturn(new ListOrganizationMembersResult([
        new GetOrganizationMemberResult(
          id: '550e8400-e29b-41d4-a716-446655441711',
          organizationId: '550e8400-e29b-41d4-a716-446655441710',
          userId: '550e8400-e29b-41d4-a716-446655441712',
          isActive: true,
          joinedAt: $joinedAt,
          roleIds: ['550e8400-e29b-41d4-a716-446655441713'],
        ),
      ]));

    $provider = new ListOrganizationMembersProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441710']);

    self::assertCount(1, $output);
    self::assertInstanceOf(OrganizationMemberOutput::class, $output[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441711', $output[0]->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441710', $output[0]->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655441712', $output[0]->userId);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441713'], $output[0]->roleIds);
    self::assertSame($joinedAt->format('c'), $output[0]->joinedAt);
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
