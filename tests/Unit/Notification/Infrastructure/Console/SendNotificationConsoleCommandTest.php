<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Console;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{
  NotificationChannel,
  SendNotificationRequest,
  SentNotification
};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Notification\Infrastructure\Console\SendNotificationConsoleCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test SendNotificationConsoleCommand.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SendNotificationConsoleCommand::class)]
final class SendNotificationConsoleCommandTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';
  // #endregion

  // #region Methods
  #[Test]
  public function testConfigureDeclaresArgumentsAndOptions(): void
  {
    $definition = $this->createCommand($this->createStub(NotificationPort::class))->getDefinition();

    self::assertTrue($definition->hasArgument('type'));
    self::assertTrue($definition->hasArgument('subject'));
    self::assertTrue($definition->hasArgument('body'));
    self::assertTrue($definition->hasOption('user-id'));
    self::assertTrue($definition->hasOption('email'));
    self::assertTrue($definition->hasOption('organization-id'));
    self::assertSame(
      NotificationChannel::EMAIL->value,
      $definition->getOption('channels')->getDefault(),
    );
  }

  #[Test]
  public function testSendsThroughTheEmailChannelByDefault(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => NotificationType::ORGANIZATION_INVITATION === $request->type
        && 'You are invited' === $request->subject
        && 'Join us' === $request->body
        && [NotificationChannel::EMAIL] === $request->channels
        && 'user@example.com' === $request->recipientEmail
        && null === $request->recipientUserId
        && null === $request->organizationId))
      ->willReturn($this->sentNotification());

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::ORGANIZATION_INVITATION,
      'subject' => '  You are invited  ',
      'body' => '  Join us  ',
      '--email' => '  user@example.com  ',
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertStringContainsString('notification-id', $tester->getDisplay());
  }

  #[Test]
  public function testSendsThroughMultipleChannelsAndForwardsTheOrganization(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::EMAIL, NotificationChannel::MERCURE] === $request->channels
        && self::USER_ID === $request->recipientUserId
        && self::ORGANIZATION_ID === $request->organizationId))
      ->willReturn($this->sentNotification());

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Maintenance',
      'body' => 'Tonight',
      '--email' => 'user@example.com',
      '--user-id' => self::USER_ID,
      '--organization-id' => self::ORGANIZATION_ID,
      '--channels' => 'email, mercure',
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
  }

  #[Test]
  public function testWarnsButStillSendsOnAnUnknownType(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())->method('send')->willReturn($this->sentNotification());

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => 'totally.unknown',
      'subject' => 'Subject',
      'body' => 'Body',
      '--email' => 'user@example.com',
    ]);

    self::assertSame(Command::SUCCESS, $exitCode);
    self::assertStringContainsString('Unknown type', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheSubjectIsBlank(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => '   ',
      'body' => 'Body',
      '--email' => 'user@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Subject is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheBodyIsBlank(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Subject',
      'body' => '   ',
      '--email' => 'user@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Body is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheTypeIsBlank(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => '   ',
      'subject' => 'Subject',
      'body' => 'Body',
      '--email' => 'user@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('Type is required', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenNoChannelResolves(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Subject',
      'body' => 'Body',
      '--channels' => 'carrier-pigeon',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('No valid channel provided', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheEmailChannelHasNoRecipientEmail(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Subject',
      'body' => 'Body',
      '--channels' => 'email',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('requires --email', $tester->getDisplay());
  }

  #[Test]
  public function testFailsWhenTheMercureChannelHasNoRecipientUser(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::never())->method('send');

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Subject',
      'body' => 'Body',
      '--channels' => 'mercure',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('requires --user-id', $tester->getDisplay());
  }

  #[Test]
  public function testReportsAFailureWhenTheportThrows(): void
  {
    /** @var NotificationPort&MockObject $port */
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('mailer down'));

    $tester = new CommandTester($this->createCommand($port));

    $exitCode = $tester->execute([
      'type' => NotificationType::SYSTEM_ANNOUNCEMENT,
      'subject' => 'Subject',
      'body' => 'Body',
      '--email' => 'user@example.com',
    ]);

    self::assertSame(Command::FAILURE, $exitCode);
    self::assertStringContainsString('mailer down', $tester->getDisplay());
  }

  private function createCommand(NotificationPort $port): SendNotificationConsoleCommand
  {
    return new SendNotificationConsoleCommand(notificationPort: $port);
  }

  private function sentNotification(): SentNotification
  {
    return new SentNotification(
      id: 'notification-id',
      type: NotificationType::SYSTEM_ANNOUNCEMENT,
      subject: 'Subject',
      body: 'Body',
      channels: [NotificationChannel::EMAIL->value],
      payload: [],
      channelDelivery: [NotificationChannel::EMAIL->value => true],
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
