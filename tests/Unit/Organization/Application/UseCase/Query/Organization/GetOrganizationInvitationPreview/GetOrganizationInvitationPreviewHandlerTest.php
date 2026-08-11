<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationInvitationTokenHasher;
use Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview\{GetOrganizationInvitationPreviewHandler, GetOrganizationInvitationPreviewQuery, GetOrganizationInvitationPreviewResult};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

#[CoversClass(GetOrganizationInvitationPreviewHandler::class)]
final class GetOrganizationInvitationPreviewHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsPreviewForKnownToken(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444400';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444401';
    $email = 'member@example.com';
    $roleId = '550e8400-e29b-41d4-a716-446655444403';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444402'),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([$roleId]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($inviterUserId, 'inviter@example.com'));

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$this->role($roleId, $organizationId, 'inspector')]);

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertInstanceOf(GetOrganizationInvitationPreviewResult::class, $result);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame('Fireguard HQ', $result->organizationName);
    self::assertSame('m****r@example.com', $result->invitedEmail);
    self::assertSame('pending', $result->status);
    self::assertSame('Test User', $result->inviterDisplayName);
    self::assertSame(['inspector'], $result->roleNames);
  }

  #[Test]
  public function testInvokeResolvesRoleNamesScopedToTheInvitationOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444420';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444421';
    $firstRoleId = '550e8400-e29b-41d4-a716-446655444423';
    $secondRoleId = '550e8400-e29b-41d4-a716-446655444424';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444422'),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->with(self::callback(
        static fn (OrganizationInvitationId $id): bool => '550e8400-e29b-41d4-a716-446655444422' === (string) $id,
      ))
      ->willReturn([$firstRoleId, $secondRoleId]);

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        self::callback(static function (array $roleIds) use ($firstRoleId, $secondRoleId): bool {
          $values = [];
          foreach ($roleIds as $roleId) {
            if (!$roleId instanceof OrganizationRoleId) {
              return false;
            }

            $values[] = (string) $roleId;
          }

          return [$firstRoleId, $secondRoleId] === $values;
        }),
      )
      ->willReturn([
        $this->role($firstRoleId, $organizationId, 'inspector'),
        $this->role($secondRoleId, $organizationId, 'technicien'),
      ]);

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertSame(['inspector', 'technicien'], $result->roleNames);
  }

  #[Test]
  public function testInvokeReturnsNoRoleNamesWhenTheInvitationGrantsNoRole(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444430';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444431';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444432'),
      organizationId: new OrganizationId($organizationId),
      email: new Email('member@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([]);

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertSame([], $result->roleNames);
  }

  #[Test]
  public function testInvokeThrowsWhenTokenIsUnknown(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn(null);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::never())->method('findById');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new GetOrganizationInvitationPreviewQuery('unknown-token'));
  }

  #[Test]
  public function testInvokeThrowsWhenTokenIsBlank(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByTokenHash');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::never())->method('findById');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new GetOrganizationInvitationPreviewQuery("  \t "));
  }

  #[Test]
  public function testInvokeReportsExpiredStatusForStillPendingInvitationPastExpiry(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444410';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444411';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444412'),
      organizationId: new OrganizationId($organizationId),
      email: new Email('ab@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('-1 hour'),
      createdAt: new DateTimeImmutable('-8 days'),
      updatedAt: new DateTimeImmutable('-8 days'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);
    $invitationRepository->method('findRoleIdsForInvitation')->willReturn([]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByIdsInOrganization');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
      roleRepository: $roleRepository,
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertSame(OrganizationInvitationStatus::EXPIRED->value, $result->status);
    self::assertSame('', $result->organizationName);
    self::assertNull($result->organizationLogoUrl);
    self::assertSame('', $result->inviterDisplayName);
    // Local parts of two characters or fewer are masked wholesale.
    self::assertSame('a***@example.com', $result->invitedEmail);
  }

  private function role(string $roleId, string $organizationId, string $name): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName($name),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
      description: 'Test role',
    );
  }
}
