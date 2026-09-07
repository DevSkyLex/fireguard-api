<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization;

use DateTimeImmutable;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\{ResendOrganizationInvitationCommand, ResendOrganizationInvitationHandler};
use Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation\{RevokeOrganizationInvitationCommand, RevokeOrganizationInvitationHandler};
use Organization\Domain\Exception\OrganizationInvitationNotPendingException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationName};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

/**
 * Prevent stale invitation commands from overwriting concurrently accepted invitations.
 *
 * @category UnitTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvitationConcurrencyTest extends TestCase
{
  /**
   * @return iterable<string,array{string}> competing administrative commands
   */
  public static function operations(): iterable
  {
    yield 'revoke' => ['revoke'];
    yield 'resend' => ['resend'];
  }

  #[Test]
  #[DataProvider('operations')]
  public function concurrentAcceptanceWinsOverStaleAdministrativeCommand(string $operation): void
  {
    $orgId = '550e8400-e29b-41d4-a716-446655449701';
    $invitationId = '550e8400-e29b-41d4-a716-446655449702';
    $actor = '550e8400-e29b-41d4-a716-446655449703';
    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($orgId),
      email: new Email('member@example.com'),
      tokenHash: 'old-hash',
      invitedByUserId: $actor,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+1 day'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
    $accepted = clone $invitation;
    $accepted->accept($actor);
    $locked = false;
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::once())->method('lock')->with($orgId)->willReturnCallback(static function () use (&$locked): void { $locked = true; });
    $invitations = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $reads = 0;
    $invitations->expects(self::exactly(2))->method('findById')->willReturnCallback(static function () use (&$reads, &$locked, $invitation, $accepted): OrganizationInvitation {
      if (0 === $reads++) {
        return $invitation;
      }
      self::assertTrue($locked, 'The fresh state must be read after taking the organization lock.');

      return $accepted;
    });
    $invitations->expects(self::never())->method('save');
    $tx = $this->createMock(TransactionManagerPort::class);
    $tx->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');
    $users = $this->createStub(UserRepositoryPort::class);
    $logger = $this->createStub(LoggerPort::class);
    $this->expectException(OrganizationInvitationNotPendingException::class);
    if ('revoke' === $operation) {
      $handler = new RevokeOrganizationInvitationHandler($invitations, $users, $notifications, $logger, $tx, $events, $joins);
      $handler(new RevokeOrganizationInvitationCommand($orgId, $invitationId, $actor));
    } else {
      $organizations = $this->createStub(OrganizationRepositoryPort::class);
      $organizations->method('findById')->willReturn(Organization::reconstitute(new OrganizationId($orgId), new OrganizationName('Organization'), $actor, true, new DateTimeImmutable('-1 day')));
      $notifier = new OrganizationInvitationNotifier($notifications, 'http://localhost:4200', new OrganizationInvitationTokenHasher(), EmailTranslatorTestFactory::create());
      $handler = new ResendOrganizationInvitationHandler($invitations, $organizations, $users, $notifier, $logger, $tx, $events, $joins);
      $handler(new ResendOrganizationInvitationCommand($orgId, $invitationId, $actor));
    }
  }
}
