<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Approval;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Adapter\Approval\OrganizationApprovalMemberDirectoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationApprovalMemberDirectoryAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationApprovalMemberDirectoryAdapter::class)]
final class OrganizationApprovalMemberDirectoryAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441811';

  #[Test]
  public function testResolveMemberIdReturnsTheMembershipIdentifier(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($this->member());

    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertSame(self::MEMBER_ID, $adapter->resolveMemberId(self::ORGANIZATION_ID, 'user-1'));
  }

  #[Test]
  public function testResolveMemberIdReturnsNullWhenNoMembershipExists(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn(null);

    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertNull($adapter->resolveMemberId(self::ORGANIZATION_ID, 'user-1'));
  }

  #[Test]
  public function testResolveMemberIdReturnsNullForAMalformedOrganizationId(): void
  {
    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertNull($adapter->resolveMemberId('not-a-uuid', 'user-1'));
  }

  #[Test]
  public function testEveryMemberSatisfiesTheMemberRole(): void
  {
    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertTrue($adapter->memberSatisfiesRole(
      self::ORGANIZATION_ID,
      self::MEMBER_ID,
      OrganizationSystemRoleCatalog::MEMBER,
    ));
  }

  #[Test]
  public function testAdminRoleIsCheckedAgainstTheWildcardPermission(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findUserIdsByMemberIds')->willReturn([self::MEMBER_ID => 'user-1']);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(
      static function (string $userId, string $organizationId, string $permission) use (&$args): bool {
        $args = [$userId, $organizationId, $permission];

        return true;
      },
    );

    $adapter = new OrganizationApprovalMemberDirectoryAdapter($repository, $authorization);

    self::assertTrue($adapter->memberSatisfiesRole(
      self::ORGANIZATION_ID,
      self::MEMBER_ID,
      OrganizationSystemRoleCatalog::ADMIN,
    ));
    self::assertSame(['user-1', self::ORGANIZATION_ID, 'organization.*'], $args);
  }

  #[Test]
  public function testAdminRoleIsRefusedWhenTheMemberHasNoMatchingUser(): void
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findUserIdsByMemberIds')->willReturn([]);

    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $repository,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertFalse($adapter->memberSatisfiesRole(
      self::ORGANIZATION_ID,
      self::MEMBER_ID,
      OrganizationSystemRoleCatalog::ADMIN,
    ));
  }

  #[Test]
  public function testAdminRoleIsRefusedForAMalformedOrganizationId(): void
  {
    $adapter = new OrganizationApprovalMemberDirectoryAdapter(
      $this->createStub(OrganizationMemberRepositoryPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    self::assertFalse($adapter->memberSatisfiesRole(
      'not-a-uuid',
      self::MEMBER_ID,
      OrganizationSystemRoleCatalog::ADMIN,
    ));
  }

  /**
   * Reconstitutes an active membership.
   */
  private function member(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString(self::MEMBER_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      userId: 'user-1',
      isActive: true,
      joinedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
    );
  }
}
