<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{GetOrganizationInvitationResult, ListOrganizationInvitationsQuery};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationInvitationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

use function iterator_to_array;

#[CoversClass(ListOrganizationInvitationsProvider::class)]
final class ListOrganizationInvitationsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441950'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListOrganizationInvitationsProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(0.0, $output->getTotalItems());
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441950'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441950', '550e8400-e29b-41d4-a716-446655441951', 'organization.members.manage')
      ->willReturn(false);

    $provider = new ListOrganizationInvitationsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441951']);
  }

  #[Test]
  public function testProvideMapsInvitationsResult(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-11T09:00:00+00:00');
    $expiresAt = new DateTimeImmutable('2026-02-18T09:00:00+00:00');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441950'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $invitationsResult = new PaginatedResult(
      items: [
        new GetOrganizationInvitationResult(
          id: '550e8400-e29b-41d4-a716-446655441952',
          organizationId: '550e8400-e29b-41d4-a716-446655441951',
          email: 'member@example.com',
          status: 'pending',
          invitedByUserId: '550e8400-e29b-41d4-a716-446655441950',
          acceptedByUserId: null,
          revokedByUserId: null,
          expiresAt: $expiresAt,
          createdAt: $createdAt,
          updatedAt: $createdAt,
          acceptedAt: null,
          revokedAt: null,
          roleIds: ['550e8400-e29b-41d4-a716-446655441953'],
        ),
      ],
      total: 1,
      limit: 1,
      offset: 0,
    );

    /** @var QueryBusPort&Stub $queryBus */
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static fn (object $query): object => $query instanceof ListOrganizationInvitationsQuery
        ? $invitationsResult
        : new GetUserResult(null),
    );

    $provider = new ListOrganizationInvitationsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441951']);

    $items = iterator_to_array($output);
    self::assertCount(1, $items);
    self::assertInstanceOf(OrganizationInvitationOutput::class, $items[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441952', $items[0]->id);
    self::assertSame('member@example.com', $items[0]->email);
    self::assertSame('pending', $items[0]->status);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441953'], $items[0]->roleIds);
    self::assertSame($expiresAt->format('c'), $items[0]->expiresAt);
    self::assertNull($items[0]->invitedByDisplayName);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationInvitationsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441960']);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'owner@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
