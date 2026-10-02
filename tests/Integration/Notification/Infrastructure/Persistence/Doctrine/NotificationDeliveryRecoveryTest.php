<?php

declare(strict_types=1);

namespace Tests\Integration\Notification\Infrastructure\Persistence\Doctrine;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Notification\Application\Contract\Notification\NotificationChannel;
use Notification\Application\Port\Outbound\{EmailNotificationChannelPort, MercureNotificationChannelPort, NotificationPreferenceRepositoryPort, NotificationRepositoryPort, RecipientDirectoryPort};
use Notification\Application\UseCase\Command\Notification\SendNotification\{SendNotificationCommand, SendNotificationHandler};
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationDeliveryReceiptRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Real PostgreSQL acknowledgement survives an outer consumer rollback. */
#[SkipDatabaseRollback]
final class NotificationDeliveryRecoveryTest extends KernelTestCase
{
  private const string ID = 'bce00000-0000-4000-8000-000000000091';

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
    $uuid = $this->createMock(UuidFactory::class);
    $uuid->expects(self::once())->method('create')->willReturn(new NotificationId(self::ID));
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

  private function clean(): void
  {
    $this->connection->executeStatement('DELETE FROM notification_delivery_receipts WHERE notification_id = ?', [self::ID]);
    $this->connection->executeStatement('DELETE FROM notifications WHERE id = ?', [self::ID]);
  }
}
