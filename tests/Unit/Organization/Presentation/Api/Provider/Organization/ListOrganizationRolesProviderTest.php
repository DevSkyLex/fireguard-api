<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{GetOrganizationRoleResult, ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationRolesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListOrganizationRolesProvider::class)]
final class ListOrganizationRolesProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListOrganizationRolesProvider(
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
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441800', '550e8400-e29b-41d4-a716-446655441810', 'organization.roles.read')
      ->willReturn(false);

    $provider = new ListOrganizationRolesProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsRolesResult(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListOrganizationRolesQuery::class))
      ->willReturn(new ListOrganizationRolesResult([
        new GetOrganizationRoleResult(
          id: '550e8400-e29b-41d4-a716-446655441811',
          organizationId: '550e8400-e29b-41d4-a716-446655441810',
          name: 'manager',
          permissions: ['organization.read', 'organization.members.manage'],
          isSystem: false,
          createdAt: $createdAt,
          description: 'Manager role',
        ),
      ]));

    $provider = new ListOrganizationRolesProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441810']);

    self::assertCount(1, $output);
    self::assertInstanceOf(OrganizationRoleOutput::class, $output[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441811', $output[0]->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441810', $output[0]->organizationId);
    self::assertSame('manager', $output[0]->name);
    self::assertCount(2, $output[0]->permissions);
    self::assertInstanceOf(OrganizationPermissionOutput::class, $output[0]->permissions[0]);
    self::assertSame('organization.read', $output[0]->permissions[0]->name);
    self::assertSame('View organization details', $output[0]->permissions[0]->description);
    self::assertSame('organization.members.manage', $output[0]->permissions[1]->name);
    self::assertSame('Manage organization members (add, invite, revoke)', $output[0]->permissions[1]->description);
    self::assertFalse($output[0]->isSystem);
    self::assertSame($createdAt->format('c'), $output[0]->createdAt);
    self::assertSame('Manager role', $output[0]->description);
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
