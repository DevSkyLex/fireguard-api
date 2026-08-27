<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationPermissionGrantGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership\{TransferOrganizationOwnershipCommand, TransferOrganizationOwnershipHandler, TransferOrganizationOwnershipResult};
use Organization\Domain\Event\Organization\OrganizationOwnershipTransferredEvent;
use Organization\Domain\Event\Role\OrganizationRoleAssignedEvent;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationArchivedException, OrganizationDeletionConfirmationMismatchException, OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationOwnershipUnchangedException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};

#[CoversClass(TransferOrganizationOwnershipHandler::class)]
final class TransferOrganizationOwnershipHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440500';

  /**
   * The slug OrganizationSlug::fromName() derives from "Fireguard Lyon",
   * the display name used by activeOrganization() below.
   */
  private const string ORG_SLUG = 'fireguard-lyon';

  private const string CURRENT_OWNER_ID = '550e8400-e29b-41d4-a716-446655440501';

  private const string OWNER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440505';

  private const string NEW_OWNER_ID = '550e8400-e29b-41d4-a716-446655440502';

  private const string NEW_OWNER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440503';

  private const string ADMIN_ROLE_ID = '550e8400-e29b-41d4-a716-446655440504';

  private const string OUTSIDER_ID = '550e8400-e29b-41d4-a716-446655440506';

  #[Test]
  public function testInvokeTransfersOwnershipAndGrantsAdminRoleWhenMissing(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();
    $adminRole = $this->adminRole();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save')->with($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::equalTo(new OrganizationMemberId(self::NEW_OWNER_MEMBER_ID)))
      ->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::equalTo(new OrganizationMemberId(self::NEW_OWNER_MEMBER_ID)),
        self::equalTo(new OrganizationRoleId(self::ADMIN_ROLE_ID)),
      );

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(self::isInstanceOf(OrganizationId::class), self::equalTo(new OrganizationRoleName('admin')))
      ->willReturn($adminRole);

    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->with(self::CURRENT_OWNER_ID, self::ORG_ID, [self::ADMIN_ROLE_ID]);

    $dispatchedEvents = [];
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $event) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = $event;
      });

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $result = $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));

    self::assertInstanceOf(TransferOrganizationOwnershipResult::class, $result);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame(self::CURRENT_OWNER_ID, $result->previousOwnerUserId);
    self::assertSame(self::NEW_OWNER_ID, $result->newOwnerUserId);
    self::assertSame(self::NEW_OWNER_ID, $organization->ownerUserId());

    self::assertCount(2, $dispatchedEvents);
    self::assertInstanceOf(OrganizationOwnershipTransferredEvent::class, $dispatchedEvents[0]);
    self::assertSame(self::ORG_ID, $dispatchedEvents[0]->organizationId);
    self::assertSame(self::CURRENT_OWNER_ID, $dispatchedEvents[0]->previousOwnerUserId);
    self::assertSame(self::NEW_OWNER_ID, $dispatchedEvents[0]->newOwnerUserId);

    self::assertInstanceOf(OrganizationRoleAssignedEvent::class, $dispatchedEvents[1]);
    self::assertSame(self::ORG_ID, $dispatchedEvents[1]->organizationId);
    self::assertSame(self::NEW_OWNER_MEMBER_ID, $dispatchedEvents[1]->memberId);
    self::assertSame(self::ADMIN_ROLE_ID, $dispatchedEvents[1]->roleId);
    self::assertSame('admin', $dispatchedEvents[1]->roleName);
  }

  #[Test]
  public function testInvokeDoesNotReassignAdminRoleWhenAlreadyHeld(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();
    $adminRole = $this->adminRole();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::once())->method('findRoleIdsForMember')->willReturn([self::ADMIN_ROLE_ID]);
    $memberRepository->expects(self::never())->method('assignRole');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn($adminRole);

    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(OrganizationOwnershipTransferredEvent::class));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeLogsAndSkipsWhenAdminSystemRoleMissing(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::never())->method('findRoleIdsForMember');
    $memberRepository->expects(self::never())->method('assignRole');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn(null);

    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(OrganizationOwnershipTransferredEvent::class));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Organization ownership transferred but no "admin" system role exists to grant.',
        [
          'organizationId' => self::ORG_ID,
          'newOwnerUserId' => self::NEW_OWNER_ID,
        ],
      );

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeTransfersOwnershipAndSkipsGrantWhenActingOwnerLacksPermission(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();
    $adminRole = $this->adminRole();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::once())->method('findRoleIdsForMember')->willReturn([]);
    // The no-privilege-escalation guard refuses the grant: the acting owner
    // no longer holds every permission the "admin" role carries. assignRole
    // must never be called.
    $memberRepository->expects(self::never())->method('assignRole');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn($adminRole);

    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->with(self::CURRENT_OWNER_ID, self::ORG_ID, [self::ADMIN_ROLE_ID])
      ->willThrowException(OrganizationAccessDeniedException::cannotGrantPermission('organization.*'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(OrganizationOwnershipTransferredEvent::class));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Organization ownership transferred but granting the "admin" system role to the new owner was skipped.',
        self::callback(static function (array $context): bool {
          return self::ORG_ID === ($context['organizationId'] ?? null)
            && self::NEW_OWNER_ID === ($context['newOwnerUserId'] ?? null)
            && self::CURRENT_OWNER_ID === ($context['actingUserId'] ?? null)
            && isset($context['error']);
        }),
      );

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    // The transfer itself must succeed even though the role grant was refused.
    $result = $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));

    self::assertInstanceOf(TransferOrganizationOwnershipResult::class, $result);
    self::assertSame(self::NEW_OWNER_ID, $organization->ownerUserId());
  }

  #[Test]
  public function testInvokeTransfersOwnershipAndLogsWhenAssignRoleFailsUnexpectedly(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();
    $adminRole = $this->adminRole();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::once())->method('findRoleIdsForMember')->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->willThrowException(new RuntimeException('Persistence failure while assigning the role.'));

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByOrganizationAndName')->willReturn($adminRole);

    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())->method('assertCanAssignRoles');

    // Only the ownership-transferred event is dispatched: assignRole failed
    // before the role-assigned event would have been dispatched.
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(OrganizationOwnershipTransferredEvent::class));

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Organization ownership transferred but granting the "admin" system role to the new owner was skipped.',
        self::callback(static function (array $context): bool {
          return self::ORG_ID === ($context['organizationId'] ?? null)
            && self::NEW_OWNER_ID === ($context['newOwnerUserId'] ?? null)
            && 'Persistence failure while assigning the role.' === ($context['error'] ?? null);
        }),
      );

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    // The already-committed ownership transfer must still be reported as a
    // success: the caller gets the result, not an error, when only the
    // best-effort role grant fails.
    $result = $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));

    self::assertInstanceOf(TransferOrganizationOwnershipResult::class, $result);
    self::assertSame(self::NEW_OWNER_ID, $result->newOwnerUserId);
    self::assertSame(self::NEW_OWNER_ID, $organization->ownerUserId());
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationAndUser');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenActingUserIsNotAnActiveMember(): void
  {
    $organization = $this->activeOrganization();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    // Resolved BEFORE the slug confirmation is validated, and BEFORE the
    // owner check: a stranger with no membership at all is refused with the
    // same exception a nonexistent organization would produce (404 at the
    // Presentation layer), so they cannot use the response to confirm the
    // organization exists nor validate slug guesses against it.
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->with(self::isInstanceOf(OrganizationId::class), self::OUTSIDER_ID)
      ->willReturn(null);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::OUTSIDER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      // A wrong slug guess must never even be compared for a non-member: the
      // membership gate is checked first, so this value is never reached.
      slugConfirmation: 'a-wrong-slug-guess',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenActingUserMembershipIsInactive(): void
  {
    $organization = $this->activeOrganization();

    $inactiveActingMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::OWNER_MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::CURRENT_OWNER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($inactiveActingMember);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugConfirmationMissing(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($ownerMember);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugConfirmationMismatched(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($ownerMember);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: 'not-the-right-slug',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenActingUserIsAnActiveMemberButNotCurrentOwner(): void
  {
    $organization = $this->activeOrganization();

    $notOwnerMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::NEW_OWNER_MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::NEW_OWNER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    // An active member (just not the owner) legitimately knows the
    // organization exists, so this reaches the owner check and is refused
    // with 403 rather than the 404 a non-member gets.
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($notOwnerMember);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    // A caller who holds every organization.* permission but is not the
    // owner must still be refused: this check bypasses RBAC entirely.
    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::NEW_OWNER_ID,
      newOwnerUserId: self::CURRENT_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNewOwnerIsNotAnActiveMember(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        default => null,
      });

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNewOwnerMembershipIsInactive(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();

    $inactiveMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::NEW_OWNER_MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::NEW_OWNER_ID,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $inactiveMember,
        default => null,
      });

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationIsArchived(): void
  {
    $organization = $this->activeOrganization();
    $organization->archive();
    $ownerMember = $this->activeOwnerMember();
    $newOwnerMember = $this->activeNewOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        self::NEW_OWNER_ID => $newOwnerMember,
        default => null,
      });
    $memberRepository->expects(self::never())->method('assignRole');

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationArchivedException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::NEW_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNewOwnerIsAlreadyTheOwner(): void
  {
    $organization = $this->activeOrganization();
    $ownerMember = $this->activeOwnerMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('findByOrganizationAndUser')
      ->willReturnCallback(static fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => match ($userId) {
        self::CURRENT_OWNER_ID => $ownerMember,
        default => null,
      });

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanAssignRoles');
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new TransferOrganizationOwnershipHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      grantGuard: $grantGuard,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );

    $this->expectException(OrganizationOwnershipUnchangedException::class);

    $handler->__invoke(new TransferOrganizationOwnershipCommand(
      organizationId: self::ORG_ID,
      actingUserId: self::CURRENT_OWNER_ID,
      newOwnerUserId: self::CURRENT_OWNER_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  private function activeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lyon'),
      createdByUserId: self::CURRENT_OWNER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      ownerUserId: self::CURRENT_OWNER_ID,
    );
  }

  private function activeOwnerMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::OWNER_MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::CURRENT_OWNER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );
  }

  private function activeNewOwnerMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::NEW_OWNER_MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::NEW_OWNER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );
  }

  private function adminRole(): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ADMIN_ROLE_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName('admin'),
      permissions: ['organization.*'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
  }
}
