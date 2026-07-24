<?php

declare(strict_types=1);

namespace Tests\Functional\Notification\Infrastructure\Console;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Infrastructure\Console\SendNotificationConsoleCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkApplication;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test SendNotificationConsoleCommandTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SendNotificationConsoleCommand::class)]
final class SendNotificationConsoleCommandTest extends KernelTestCase
{
  // #region Properties
  /**
   * Property application.
   *
   * The kernel-backed console application, wired with the real notification port.
   */
  private FrameworkApplication $application;
  // #endregion

  // #region Setup
  /**
   * Method setUp.
   *
   * Boots the kernel and builds a console application from it.
   */
  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    $this->application = new FrameworkApplication($kernel);
  }
  // #endregion

  // #region Structure
  /**
   * Tests that the command is registered under its name and alias.
   */
  #[Test]
  public function commandIsRegisteredWithNameAndAlias(): void
  {
    self::assertTrue($this->application->has('app:notification:send'));
    self::assertTrue($this->application->has('notification:send'));
  }
  // #endregion

  // #region Happy path
  /**
   * Tests that a valid request is dispatched through the port and reported.
   */
  #[Test]
  public function sendsNotificationSuccessfullyViaEmail(): void
  {
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'organization.invitation' === $request->type
        && 'You are invited' === $request->subject
        && 'invitee@example.com' === $request->recipientEmail
        && [NotificationChannel::EMAIL] === $request->channels))
      ->willReturn($this->sentNotification('notif-happy-1', 'organization.invitation'));

    $tester = $this->mockedTester($port);
    $tester->execute([
      'type' => 'organization.invitation',
      'subject' => 'You are invited',
      'body' => 'Join us',
      '--email' => 'invitee@example.com',
    ]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('notif-happy-1', $tester->getDisplay());
  }

  /**
   * Tests that an unknown type warns but still dispatches the notification.
   */
  #[Test]
  public function warnsButStillSendsOnUnknownType(): void
  {
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->willReturn($this->sentNotification('notif-unknown-1', 'custom.unknown'));

    $tester = $this->mockedTester($port);
    $tester->execute([
      'type' => 'custom.unknown',
      'subject' => 'Heads up',
      'body' => 'Body',
      '--email' => 'invitee@example.com',
    ]);

    $tester->assertCommandIsSuccessful();
    self::assertStringContainsString('Unknown type', $tester->getDisplay());
  }
  // #endregion

  // #region Failure branches
  /**
   * Tests that a blank type argument is rejected before dispatch.
   */
  #[Test]
  public function failsWhenTypeIsBlank(): void
  {
    $tester = new CommandTester($this->realCommand());
    $tester->execute([
      'type' => '',
      'subject' => 'Subject',
      'body' => 'Body',
      '--email' => 'invitee@example.com',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Type is required.', $tester->getDisplay());
  }

  /**
   * Tests that the email channel requires the --email option.
   */
  #[Test]
  public function failsWhenEmailChannelHasNoEmailOption(): void
  {
    $tester = new CommandTester($this->realCommand());
    $tester->execute([
      'type' => 'system.announcement',
      'subject' => 'Subject',
      'body' => 'Body',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('The email channel requires --email.', $tester->getDisplay());
  }

  /**
   * Tests that the mercure channel requires the --user-id option.
   */
  #[Test]
  public function failsWhenMercureChannelHasNoUserId(): void
  {
    $tester = new CommandTester($this->realCommand());
    $tester->execute([
      'type' => 'system.announcement',
      'subject' => 'Subject',
      'body' => 'Body',
      '--channels' => 'mercure',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('The mercure channel requires --user-id.', $tester->getDisplay());
  }

  /**
   * Tests that an unresolvable channel list is rejected.
   */
  #[Test]
  public function failsWhenNoValidChannelProvided(): void
  {
    $tester = new CommandTester($this->realCommand());
    $tester->execute([
      'type' => 'system.announcement',
      'subject' => 'Subject',
      'body' => 'Body',
      '--channels' => 'sms',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('No valid channel', $tester->getDisplay());
  }

  /**
   * Tests that a port failure is caught and reported as a command failure.
   */
  #[Test]
  public function failsWhenPortThrows(): void
  {
    $port = $this->createMock(NotificationPort::class);
    $port->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('transport down'));

    $tester = $this->mockedTester($port);
    $tester->execute([
      'type' => 'system.announcement',
      'subject' => 'Subject',
      'body' => 'Body',
      '--email' => 'invitee@example.com',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString('Failed to send notification', $tester->getDisplay());
    self::assertStringContainsString('transport down', $tester->getDisplay());
  }
  // #endregion

  // #region Helpers
  /**
   * Method realCommand.
   *
   * Fetches the container-wired command from the kernel application.
   */
  private function realCommand(): Command
  {
    return $this->application->find('app:notification:send');
  }

  /**
   * Method mockedTester.
   *
   * Builds a command tester around a fresh command bound to a mocked port.
   *
   * @param NotificationPort $port the mocked inbound port
   */
  private function mockedTester(NotificationPort $port): CommandTester
  {
    $command = new SendNotificationConsoleCommand($port);
    new ConsoleApplication()->add($command);

    return new CommandTester($command);
  }

  /**
   * Method sentNotification.
   *
   * Builds a delivered email notification result for port stubbing.
   *
   * @param string $id the notification identifier
   * @param string $type the notification type
   */
  private function sentNotification(string $id, string $type): SentNotification
  {
    return new SentNotification(
      id: $id,
      type: $type,
      subject: 'You are invited',
      body: 'Join us',
      channels: ['email'],
      payload: [],
      channelDelivery: ['email' => true],
      createdAt: new DateTimeImmutable(),
      recipientEmail: 'invitee@example.com',
    );
  }
  // #endregion
}
