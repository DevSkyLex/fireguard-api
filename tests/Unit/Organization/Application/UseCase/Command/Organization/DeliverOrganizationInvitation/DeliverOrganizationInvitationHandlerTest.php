<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\SentNotification;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation\{DeliverOrganizationInvitationCommand, DeliverOrganizationInvitationHandler};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationName};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use User\Application\Port\Outbound\UserRepositoryPort;

final class DeliverOrganizationInvitationHandlerTest extends TestCase
{
  private const string ID = 'bc000000-0000-4000-8000-000000000096';

  public function testFailedDeliveryCanRetryAndSuccessfulDeliveryIsDeduplicated(): void
  {
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::exactly(2))->method('send')->willReturnOnConsecutiveCalls(
      new SentNotification('a', 'invitation', '', '', ['email'], [], ['email' => false], new DateTimeImmutable()),
      new SentNotification('b', 'invitation', '', '', ['email'], [], ['email' => true], new DateTimeImmutable()),
    );
    $handler = $this->handler($this->invitation(), $port);
    $command = new DeliverOrganizationInvitationCommand(self::ID, 'https://app.example.test/accept?token=test', 'hash');

    try {
      $handler($command);
      self::fail('A failed email must remain retryable.');
    } catch (RuntimeException $exception) {
      self::assertSame('The invitation email could not be delivered.', $exception->getMessage());
    }
    $handler($command);
    $handler($command);
  }

  public function testRotatedExpiredAndRevokedInvitationsAreNotDelivered(): void
  {
    $revoked = $this->invitation();
    $revoked->revoke(self::ID);
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');
    foreach ([$this->invitation('new-hash'), $this->invitation(expires: new DateTimeImmutable('-1 day')), $revoked] as $invitation) {
      $this->handler($invitation, $port)(new DeliverOrganizationInvitationCommand(self::ID, 'https://app.example.test/accept?token=test', 'hash'));
    }
  }

  private function invitation(string $hash = 'hash', ?DateTimeImmutable $expires = null): OrganizationInvitation
  {
    return OrganizationInvitation::create(
      OrganizationInvitationId::fromString(self::ID),
      OrganizationId::fromString(self::ID),
      new Email('invitee@example.test'),
      $hash,
      self::ID,
      $expires ?? new DateTimeImmutable('+1 day'),
    );
  }

  private function handler(OrganizationInvitation $invitation, NotificationPort $port): DeliverOrganizationInvitationHandler
  {
    $invitations = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitations->method('findById')->willReturn($invitation);
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(Organization::create(OrganizationId::fromString(self::ID), new OrganizationName('Example'), self::ID));
    $consumer = new class () implements IdempotentConsumerPort {
      /**
       * @var array<string, true>
       */
      private array $receipts = [];

      public function consume(string $eventId, string $consumer, callable $operation): bool
      {
        $key = $eventId . $consumer;
        if (isset($this->receipts[$key])) {
          return false;
        }
        $operation();
        $this->receipts[$key] = true;

        return true;
      }
    };

    return new DeliverOrganizationInvitationHandler(
      $invitations,
      $organizations,
      $this->createStub(UserRepositoryPort::class),
      new OrganizationInvitationNotifier($port, 'https://app.example.test', new OrganizationInvitationTokenHasher(), $this->createStub(TranslatorInterface::class)),
      $consumer,
    );
  }
}
