<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Contract\Member\OrganizationMemberGrant;
use Organization\Application\Port\Inbound\{OrganizationJoinAccessPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort};
use Organization\Application\Service\OrganizationMemberGrantGuardService;
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationJoinException};
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationJoin\OrganizationAccessPolicy;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationJoinMode};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

/**
 * Test OrganizationMemberGrantGuardServiceTest.
 *
 * @category Unit Tests
 */
#[CoversClass(OrganizationMemberGrantGuardService::class)]
final class OrganizationMemberGrantGuardServiceTest extends TestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-446655440001';

  private const string INVITATION = '550e8400-e29b-41d4-a716-446655440002';

  private const string ROLE = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function actorGrantsCheckTheResolvedRoleSet(): void
  {
    $permissions = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $permissions->expects(self::once())->method('assertCanAssignRoles')->with('actor', self::ORG, [self::ROLE]);
    $service = new OrganizationMemberGrantGuardService($permissions, $this->createStub(OrganizationInvitationRepositoryPort::class), $this->createStub(OrganizationJoinRepositoryPort::class), $this->createStub(OrganizationJoinAccessPort::class));

    $service->assertCanGrant(OrganizationMemberGrant::forActor('actor'), self::ORG, 'member@example.test', [self::ROLE]);
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function invitationProvenance(): iterable
  {
    yield 'exact grant' => ['exact', true];
    yield 'different roles' => ['roles', false];
    yield 'different recipient' => ['recipient', false];
    yield 'different organization' => ['organization', false];
    yield 'expired' => ['expired', false];
    yield 'revoked' => ['revoked', false];
    yield 'missing invitation' => ['missing', false];
  }

  #[Test]
  #[DataProvider('invitationProvenance')]
  public function invitationGrantsRequireTheirPersistedProvenance(string $case, bool $allowed): void
  {
    $invitation = OrganizationInvitation::create(
      OrganizationInvitationId::fromString(self::INVITATION),
      OrganizationId::fromString('organization' === $case ? self::INVITATION : self::ORG),
      new Email('recipient' === $case ? 'other@example.test' : 'member@example.test'),
      'stored-hash',
      'original-inviter',
      new DateTimeImmutable('expired' === $case ? '-1 minute' : '+1 day'),
    );
    if ('revoked' === $case) {
      $invitation->revoke('original-inviter');
    }
    $invitations = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitations->method('findById')->willReturn('missing' === $case ? null : $invitation);
    $invitations->method('findRoleIdsForInvitation')->willReturn('roles' === $case ? [self::INVITATION] : [self::ROLE]);
    $permissions = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $permissions->expects(self::never())->method('assertCanAssignRoles');
    $service = new OrganizationMemberGrantGuardService($permissions, $invitations, $this->createStub(OrganizationJoinRepositoryPort::class), $this->createStub(OrganizationJoinAccessPort::class));
    if (!$allowed) {
      $this->expectException(OrganizationInvitationNotFoundException::class);
    }

    $service->assertCanGrant(OrganizationMemberGrant::acceptedInvitation(self::INVITATION), self::ORG, 'member@example.test', [self::ROLE]);
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function automaticProvenance(): iterable
  {
    yield 'exact role' => ['exact', true];
    yield 'different role' => ['roles', false];
    yield 'different policy mode' => ['mode', false];
    yield 'changed policy role' => ['policy', false];
    yield 'foreign policy' => ['organization', false];
  }

  #[Test]
  #[DataProvider('automaticProvenance')]
  public function automaticGrantsRequireTheCurrentEligiblePolicyRole(string $case, bool $allowed): void
  {
    $joins = $this->createStub(OrganizationJoinRepositoryPort::class);
    $joins->method('policy')->willReturn(new OrganizationAccessPolicy(
      'organization' === $case ? self::INVITATION : self::ORG,
      'mode' === $case ? OrganizationJoinMode::INVITATION_ONLY : OrganizationJoinMode::AUTOMATIC,
      'policy' === $case ? self::INVITATION : self::ROLE,
    ));
    $access = $this->createMock(OrganizationJoinAccessPort::class);
    $access->expects($allowed ? self::once() : self::never())->method('assertEligibleRole')->with(self::ORG, self::ROLE)->willReturn('member');
    $permissions = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $permissions->expects(self::never())->method('assertCanAssignRoles');
    $service = new OrganizationMemberGrantGuardService($permissions, $this->createStub(OrganizationInvitationRepositoryPort::class), $joins, $access);
    if (!$allowed) {
      $this->expectException(OrganizationJoinException::class);
    }

    $service->assertCanGrant(OrganizationMemberGrant::automaticJoin(self::ROLE), self::ORG, 'member@example.test', ['roles' === $case ? self::INVITATION : self::ROLE]);
  }

  #[Test]
  public function operatorGrantIsAnExplicitTrustedFlow(): void
  {
    $permissions = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $permissions->expects(self::never())->method('assertCanAssignRoles');
    $invitations = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitations->expects(self::never())->method('findById');
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::never())->method('policy');

    new OrganizationMemberGrantGuardService($permissions, $invitations, $joins, $this->createStub(OrganizationJoinAccessPort::class))
      ->assertCanGrant(OrganizationMemberGrant::operator(), self::ORG, 'member@example.test', [self::ROLE]);
  }

  #[Test]
  public function ordinaryGrantRequiresAnActor(): void
  {
    $this->expectException(InvalidArgumentException::class);
    OrganizationMemberGrant::forActor(' ');
  }
}
