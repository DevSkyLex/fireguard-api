<?php

declare(strict_types=1);

namespace Tests\Integration\Notification\Infrastructure\Persistence\Doctrine;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Exception\NotificationDeliveryFailedException;
use Notification\Application\Port\Outbound\Internal\{EmailNotificationChannelPort, MercureNotificationChannelPort, NotificationPreferenceRepositoryPort, NotificationRepositoryPort};
use Notification\Application\Port\Outbound\RecipientDirectoryPort;
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\DeliverRequestedNotification\{DeliverRequestedNotificationCommand, DeliverRequestedNotificationHandler};
use Notification\Application\UseCase\Command\Notification\SendNotification\{SendNotificationCommand, SendNotificationHandler};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationDeliveryReceiptRepository;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use RuntimeException;
use Shared\Application\Port\Outbound\{LoggerPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\DoctrineIdempotentConsumerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function hash;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/** Real PostgreSQL acknowledgement survives an outer consumer rollback. */
#[SkipDatabaseRollback]
final class NotificationDeliveryRecoveryTest extends KernelTestCase
{
  private const string ID = 'bce00000-0000-4000-8000-000000000091';

  private const string EVENT = 'notification-recipient-recovery-regression';

  private const string USER = 'bce00000-0000-4000-8000-000000000092';

  private Connection $connection;

  protected function setUp(): void
  {
    self::bootKernel();
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);
    $this->connection = $connection;
    $this->clean();
  }

  protected function tearDown(): void
  {
    while ($this->connection->isTransactionActive()) {
      $this->connection->rollBack();
    }
    $this->clean();
    parent::tearDown();
  }

  #[Test]
  public function retryKeepsOneInboxRowAndDoesNotRepeatAnAcknowledgedChannel(): void
  {
    $emailAttempts = 0;
    $email = $this->createMock(EmailNotificationChannelPort::class);
    $email->expects(self::exactly(2))->method('send')->willReturnCallback(static function () use (&$emailAttempts): void {
      if (1 === ++$emailAttempts) {
        throw new RuntimeException('Temporary SMTP failure');
      }
    });
    $mercure = $this->createMock(MercureNotificationChannelPort::class);
    $mercure->expects(self::once())->method('publish');
    $uuid = $this->createMock(UuidGeneratorPort::class);
    $uuid->expects(self::once())->method('generate')->willReturn(self::ID);
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $receipts = new NotificationDeliveryReceiptRepository($this->connection);
    $handler = new SendNotificationHandler($repository, $this->createStub(NotificationPreferenceRepositoryPort::class), $email, $mercure, $this->createStub(RecipientDirectoryPort::class), $this->createStub(LoggerPort::class), $uuid, $receipts);
    $command = new SendNotificationCommand(type: 'maintenance.reminder', subject: 'Due equipment', body: 'Inspect equipment', channels: [NotificationChannel::MERCURE, NotificationChannel::EMAIL], payload: [], recipientUserId: 'bce00000-0000-4000-8000-000000000092', recipientEmail: 'recipient@example.com', idempotencyKey: 'recovery-regression');

    $this->connection->beginTransaction();
    $first = $handler($command);
    self::assertSame(['mercure' => 'delivered', 'email' => 'failed'], $first->channelStatus);
    $this->connection->rollBack();
    $second = $handler($command);
    self::assertSame($first->id, $second->id);
    self::assertSame(['mercure' => 'delivered', 'email' => 'delivered'], $second->channelStatus);
    $third = $handler($command);
    self::assertSame($second->channelStatus, $third->channelStatus);
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM notifications WHERE id = ?', [self::ID]));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM notification_delivery_receipts WHERE notification_id = ?', [self::ID]));
  }

  /**
   * @param string $change the directory or preference change after the failed email
   */
  #[Test]
  #[DataProvider('recipientChanges')]
  public function replayReconcilesRecipientChangesWithoutRepeatingAcknowledgedMercure(string $change): void
  {
    $attempts = 0;
    $email = $this->createMock(EmailNotificationChannelPort::class);
    $email->expects('address' === $change ? self::exactly(2) : self::once())->method('send')
      ->willReturnCallback(static function (Notification $notification) use (&$attempts): void {
        if (1 === ++$attempts) {
          throw new RuntimeException('Temporary SMTP failure');
        }
        self::assertSame('updated@example.com', (string) $notification->recipientEmail());
      });
    $mercure = $this->createMock(MercureNotificationChannelPort::class);
    $mercure->expects(self::once())->method('publish');
    $directory = $this->createMock(RecipientDirectoryPort::class);
    $directory->expects(self::exactly(2))->method('emailForUserId')->with(self::USER)
      ->willReturn('original@example.com', 'missing' === $change ? null : 'updated@example.com');
    $preferences = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $preferences->expects(self::exactly(2))->method('findByUserIdAndCategory')->willReturn(
      null,
      'preference' === $change ? NotificationPreference::create(self::USER, 'organization', emailEnabled: false) : null,
    );
    $uuid = $this->createMock(UuidGeneratorPort::class);
    $uuid->expects(self::once())->method('generate')->willReturn(self::ID);
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $handler = new SendNotificationHandler($repository, $preferences, $email, $mercure, $directory, $this->createStub(LoggerPort::class), $uuid, new NotificationDeliveryReceiptRepository($this->connection));
    $consumer = new DeliverRequestedNotificationHandler(new NotificationService($handler), new DoctrineIdempotentConsumerAdapter($this->connection));
    $command = new DeliverRequestedNotificationCommand(self::EVENT, new SendNotificationRequest('organization_plan_over_quota', 'Plan changed', 'Usage snapshot', [NotificationChannel::MERCURE, NotificationChannel::EMAIL], recipientUserId: self::USER));

    try {
      $consumer($command);
      self::fail('The first failed email must retry the durable consumer.');
    } catch (NotificationDeliveryFailedException) {
      self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$this->consumerReceiptId()]));
    }
    $consumer($command);
    $consumer($command);
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$this->consumerReceiptId()]));
    self::assertSame(1, $this->connection->fetchOne('SELECT COUNT(*) FROM notifications WHERE id = ?', [self::ID]));
    $statuses = $this->connection->fetchOne('SELECT channel_status FROM notification_delivery_receipts WHERE notification_id = ?', [self::ID]);
    self::assertIsString($statuses);
    self::assertSame(['email' => 'address' === $change ? 'delivered' : 'suppressed', 'mercure' => 'delivered'], json_decode($statuses, true, flags: JSON_THROW_ON_ERROR));
  }

  /**
   * @return array<string, array{string}> current recipient changes
   */
  public static function recipientChanges(): array
  {
    return ['email unavailable' => ['missing'], 'address changed' => ['address'], 'email disabled' => ['preference']];
  }

  private function consumerReceiptId(): string
  {
    return hash('sha256', self::EVENT . "\0notification.delivery:organization_plan_over_quota:" . self::USER);
  }

  private function clean(): void
  {
    $this->connection->executeStatement('DELETE FROM consumed_events WHERE id = ?', [$this->consumerReceiptId()]);
    $this->connection->executeStatement('DELETE FROM notification_delivery_receipts WHERE notification_id = ?', [self::ID]);
    $this->connection->executeStatement('DELETE FROM notifications WHERE id = ?', [self::ID]);
  }
}
