<?php

declare(strict_types=1);

namespace Tests\Integration\Webhook\Infrastructure\Delivery;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Doctrine\Persistence\ConnectionRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use RuntimeException;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\DeliverWebhookCommand;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};
use Webhook\Infrastructure\Adapter\Delivery\{DoctrineWebhookDeliveryScheduleAdapter, DoctrineWebhookDeliveryWorkAdapter};
use Webhook\Infrastructure\Adapter\Messenger\MessengerWebhookDeliveryQueueAdapter;
use Webhook\Infrastructure\EventSubscriber\WebhookTechnicalFailureSubscriber;
use Webhook\Infrastructure\Persistence\Doctrine\Repository\WebhookDeliveryRepository;

use function iterator_to_array;

/** Actual PostgreSQL commits and native Doctrine transport, outside DAMA's outer transaction. */
final class WebhookDeliveryAtomicityTest extends KernelTestCase
{
  private const string DELIVERY = 'bd000000-0000-4000-8000-000000000001';

  private const string SUBSCRIPTION = 'bd000000-0000-4000-8000-000000000002';

  private const string TOKEN_A = 'bd000000-0000-4000-8000-000000000003';

  private const string TOKEN_B = 'bd000000-0000-4000-8000-000000000004';

  private const string TOKEN_C = 'bd000000-0000-4000-8000-000000000005';

  private const string TOKEN_D = 'bd000000-0000-4000-8000-000000000006';

  private const string QUEUE = 'webhook_atomicity_regression';

  private Connection $a;

  private Connection $b;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  private string $organization;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->sender = $this->transport($this->a);
    $this->receiver = $this->transport($this->b);
    $this->sender->setup();
    $this->clearRows();
    $organization = $this->a->fetchOne('SELECT id FROM organizations ORDER BY id LIMIT 1');
    self::assertIsString($organization);
    $this->organization = $organization;
    $this->a->executeStatement('INSERT INTO webhook_subscriptions (id, organization_id, url, secret_ciphertext, event_types, is_active, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, TRUE, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [self::SUBSCRIPTION, $organization, 'https://example.test/hook', 'cipher', '["webhook.ping"]', 'Atomicity regression']);
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function commitsReservationAndNativeDoctrineEnqueueTogether(): void
  {
    $this->a->beginTransaction();
    $this->reserve();
    self::assertTrue($this->schedule()->enqueue(self::DELIVERY, 0, new DateTimeImmutable()));
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertFalse($this->b->fetchOne('SELECT id FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->a->rollBack();
    self::assertSame(0, $this->receiver->getMessageCount());

    $this->a->transactional(function (): void {
      $this->reserve();
      self::assertTrue($this->schedule()->enqueue(self::DELIVERY, 0, new DateTimeImmutable()));
    });
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertSame(0, $this->b->fetchOne('SELECT generation FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $command = $envelopes[0]->getMessage();
    self::assertInstanceOf(DeliverWebhookCommand::class, $command);
    self::assertSame(0, $command->generation());
    self::assertSame(self::TOKEN_A, $command->wakeToken());
    $this->receiver->ack($envelopes[0]);
  }

  #[Test]
  public function anEnqueueFailureRollsBackBothReservationAndManualReopen(): void
  {
    $bus = $this->createStub(MessageBusInterface::class);
    $bus->method('dispatch')->willThrowException(new RuntimeException('Sender interrupted'));

    try {
      $this->a->transactional(function () use ($bus): void {
        $this->reserve();
        $this->schedule(bus: $bus)->enqueue(self::DELIVERY, 0, new DateTimeImmutable());
      });
      self::fail('Enqueue must fail.');
    } catch (RuntimeException $exception) {
      self::assertSame('Sender interrupted', $exception->getMessage());
    }
    self::assertFalse($this->b->fetchOne('SELECT id FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->reserve();

    try {
      $this->a->transactional(function () use ($bus): void {
        self::assertSame(1, $this->work()->reopen(self::DELIVERY, self::SUBSCRIPTION, new DateTimeImmutable()));
        $this->schedule(bus: $bus)->enqueue(self::DELIVERY, 1, new DateTimeImmutable());
      });
      self::fail('Redelivery enqueue must fail.');
    } catch (RuntimeException) {
      self::assertSame(0, $this->b->fetchOne('SELECT generation FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    }
  }

  #[Test]
  public function serializesClaimsAndFencesExpiredWorkersAndOldGenerations(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    $old = $this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, $now);
    self::assertNotNull($old);
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, null, self::TOKEN_B, $now));
    self::assertTrue($this->work()->startHttpAttempt(self::DELIVERY, 0, self::TOKEN_A));
    $old->beginHttpAttempt();
    $old->markDelivered(200, $now);

    $new = $this->work($this->b)->claim(self::DELIVERY, 0, null, self::TOKEN_B, $now->modify('+91 seconds'));
    self::assertNotNull($new);
    self::assertFalse($this->work()->finish($old, 0, self::TOKEN_A));
    self::assertSame(WebhookDeliveryStatus::PENDING->value, $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->work()->reopen(self::DELIVERY, self::SUBSCRIPTION, $now));
    self::assertFalse($this->work($this->b)->finish($new, 0, self::TOKEN_B));
    self::assertNull($this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, $now));
    self::assertNotNull($this->work()->claim(self::DELIVERY, 1, null, self::TOKEN_A, $now));
  }

  #[Test]
  public function competingRecoveryWorkersEnqueueOnlyOneDurableWake(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    self::assertCount(1, $this->work()->recoverable($now, 1000));
    self::assertCount(1, $this->work($this->b)->recoverable($now, 1000));
    self::assertTrue($this->schedule()->enqueue(self::DELIVERY, 0, $now));
    self::assertFalse($this->schedule($this->b)->enqueue(self::DELIVERY, 0, $now));
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertCount(0, $this->work()->recoverable($now, 1000));
  }

  #[Test]
  public function futureOrBusyWakeDoesNotConsumeAnHttpAttempt(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    $due = $now->modify('+30 seconds');
    $this->a->executeStatement('UPDATE webhook_deliveries SET next_retry_at = ? WHERE id = ?', [$due, self::DELIVERY], ['datetime_immutable']);
    self::assertTrue($this->schedule()->enqueue(self::DELIVERY, 0, $due));
    self::assertNull($this->work()->claim(self::DELIVERY, 0, self::TOKEN_A, self::TOKEN_B, $now));
    self::assertSame(0, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $due));
    self::assertFalse($this->schedule($this->b, wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $due));
    self::assertNull($this->work()->claim(self::DELIVERY, 0, self::TOKEN_A, self::TOKEN_C, $due->modify('+1 second')));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_B, self::TOKEN_C, $due->modify('+1 second')));
    self::assertSame(0, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  public function cannotResetTheTechnicalBudgetByRecoveringWithFreshEnvelopes(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();

    for ($failure = 0; $failure < 6; ++$failure) {
      self::assertNotNull($this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, $now));
      $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_A, $now);
    }
    self::assertSame(6, $this->b->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, $now->modify('+2 hours')));
    self::assertCount(0, $this->work()->recoverable($now->modify('+2 hours'), 1000));
    self::assertSame(1, $this->work()->reopen(self::DELIVERY, self::SUBSCRIPTION, $now));
    self::assertCount(1, $this->work()->recoverable($now, 1000));
  }

  #[Test]
  public function aCrashDuringTheFifthHttpCallCannotLeadToASixth(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    $this->a->executeStatement('UPDATE webhook_deliveries SET attempts = 4 WHERE id = ?', [self::DELIVERY]);
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, $now));
    self::assertTrue($this->work()->startHttpAttempt(self::DELIVERY, 0, self::TOKEN_A));
    self::assertCount(0, $this->work()->recoverable($now->modify('+91 seconds'), 1000));
    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(5, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  public function refusesHttpClaimInsideAnOuterTransaction(): void
  {
    $this->reserve();
    $this->a->beginTransaction();
    $this->expectException(LogicException::class);
    $this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_A, new DateTimeImmutable());
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function staleTechnicalExhaustionCannotQuarantineANewerLease(?string $oldCommandToken): void
  {
    $this->reserve($oldCommandToken);
    $now = new DateTimeImmutable();
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, $oldCommandToken, self::TOKEN_A, $now));
    self::assertTrue($this->schedule($this->b, wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now->modify('+91 seconds')));
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_B, self::TOKEN_B, $now->modify('+91 seconds')));
    $this->work()->quarantine(self::DELIVERY, 0, $oldCommandToken);
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT attempt_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame('pending', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work($this->b)->technicalFailure(self::DELIVERY, 0, self::TOKEN_B, $now);
    $this->work()->quarantine(self::DELIVERY, 0, $oldCommandToken);
    self::assertSame('pending', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function aLateExhaustedEnvelopeCannotCancelTheNextHttpWake(?string $commandToken): void
  {
    $this->reserve($commandToken);
    $now = new DateTimeImmutable();
    $this->sender->send(new Envelope(new DeliverWebhookCommand(self::DELIVERY, 0, $commandToken)));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $delivery = $this->work()->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now);
    self::assertNotNull($delivery);
    self::assertTrue($this->work()->startHttpAttempt(self::DELIVERY, 0, self::TOKEN_C));
    $delivery->beginHttpAttempt();
    $due = $now->modify('+1 second');
    $delivery->recordRetryableFailure(503, 'Unexpected HTTP status 503.', $due, $now);
    $this->a->transactional(function () use ($delivery, $due): void {
      self::assertTrue($this->work()->finish($delivery, 0, self::TOKEN_C));
      self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $due));
    });

    new WebhookTechnicalFailureSubscriber($this->work($this->b))->onFailure(new WorkerMessageFailedEvent($envelopes[0], 'webhook', new RuntimeException('Late failure after the HTTP result committed')));
    $this->receiver->ack($envelopes[0]);

    self::assertSame('pending', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertFalse($this->b->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]));
    self::assertSame(1, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->b->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function anExhaustedOldEnvelopeCannotReclaimTheNextHttpCycle(?string $commandToken): void
  {
    $this->reserve($commandToken);
    $now = new DateTimeImmutable();
    $this->sender->send(new Envelope(new DeliverWebhookCommand(self::DELIVERY, 0, $commandToken), [new RedeliveryStamp(5)]));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $delivery = $this->work()->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now);
    self::assertNotNull($delivery);
    self::assertTrue($this->work()->startHttpAttempt(self::DELIVERY, 0, self::TOKEN_C));
    $delivery->beginHttpAttempt();
    $due = $now->modify('+1 second');
    $delivery->recordRetryableFailure(503, 'Unexpected HTTP status 503.', $due, $now);
    $this->a->transactional(function () use ($delivery, $due): void {
      self::assertTrue($this->work()->finish($delivery, 0, self::TOKEN_C));
      self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $due));
    });

    $reserved = $this->a->fetchAssociative('SELECT command_token, wake_token, wake_at, attempts, technical_failures, attempt_token, lease_until FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]);
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_D, $now));
    self::assertSame($reserved, $this->a->fetchAssociative('SELECT command_token, wake_token, wake_at, attempts, technical_failures, attempt_token, lease_until FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));

    $staleAttempt = $this->work($this->b)->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_D, $due->modify('+1 second'));
    if (null !== $staleAttempt) {
      $this->work($this->b)->technicalFailure(self::DELIVERY, 0, self::TOKEN_D, $due);
      new WebhookTechnicalFailureSubscriber($this->work($this->b))->onFailure(new WorkerMessageFailedEvent($envelopes[0], 'webhook', new RuntimeException('Unexpected error on an exhausted old envelope')));
    }
    $this->receiver->ack($envelopes[0]);

    self::assertNull($staleAttempt);
    self::assertSame('pending', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->a->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->a->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]));
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_B, self::TOKEN_D, $due->modify('+1 second')));
  }

  #[Test]
  public function anOlderReservationIsAdoptedOnlyByItsWakeAndNotByALegacyNullCommand(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now));
    $this->a->executeStatement('UPDATE webhook_deliveries SET command_token = NULL WHERE id = ?', [self::DELIVERY]);
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, null, self::TOKEN_C, $now));
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_A, self::TOKEN_C, $now));
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->a->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->a->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_B, self::TOKEN_C, $now));
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_C, $this->a->fetchOne('SELECT attempt_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  #[DataProvider('historicalReservationOwners')]
  public function onlyTheReservedWakeCanQuarantineAnUnadoptedHistoricalCycle(?string $storedCommandToken, ?string $staleCommandToken): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now));
    $this->a->executeStatement('UPDATE webhook_deliveries SET command_token = ? WHERE id = ?', [$storedCommandToken, self::DELIVERY]);
    $this->work($this->b)->quarantine(self::DELIVERY, 0, $staleCommandToken);
    self::assertSame('pending', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertFalse($this->a->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->a->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->a->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->a->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    new WebhookTechnicalFailureSubscriber($this->work($this->b))->onFailure(new WorkerMessageFailedEvent($envelopes[0], 'webhook', new RuntimeException('Historical wake exhausted before claiming')));
    $this->receiver->ack($envelopes[0]);
    self::assertSame('failed', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->a->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->a->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertCount(0, $this->work()->recoverable($now->modify('+2 hours'), 1000));
  }

  /**
   * @return iterable<string, array{?string, ?string}>
   */
  public static function historicalReservationOwners(): iterable
  {
    yield 'missing cycle identity and stale null envelope' => [null, null];
    yield 'missing cycle identity and stale A envelope' => [null, self::TOKEN_A];
    yield 'outdated A cycle identity and stale null envelope' => [self::TOKEN_A, null];
    yield 'outdated A cycle identity and stale A envelope' => [self::TOKEN_A, self::TOKEN_A];
  }

  #[Test]
  public function aCurrentLegacyCommandCanRetryAfterATechnicalFailure(): void
  {
    $this->reserve();
    $now = new DateTimeImmutable();
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, null, self::TOKEN_C, $now));
    $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_C, $now);
    self::assertSame(self::TOKEN_C, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 0, null, self::TOKEN_D, $now));
    self::assertNull($this->a->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->a->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work($this->b)->technicalFailure(self::DELIVERY, 0, self::TOKEN_D, $now);
    $this->work()->quarantine(self::DELIVERY, 0, null);
    self::assertSame('failed', $this->a->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(2, $this->a->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->a->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function technicalExhaustionStillQuarantinesTheCurrentCommandAndManualRedeliveryReopensIt(?string $commandToken): void
  {
    $this->reserve($commandToken);
    $now = new DateTimeImmutable();
    $this->sender->send(new Envelope(new DeliverWebhookCommand(self::DELIVERY, 0, $commandToken)));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now));
    $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_C, $now);
    new WebhookTechnicalFailureSubscriber($this->work($this->b))->onFailure(new WorkerMessageFailedEvent($envelopes[0], 'webhook', new RuntimeException('Technical retries exhausted')));
    $this->receiver->ack($envelopes[0]);

    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->b->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertCount(0, $this->work()->recoverable($now->modify('+2 hours'), 1000));
    self::assertSame(0, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));

    $this->a->transactional(function () use ($now): void {
      self::assertSame(1, $this->work()->reopen(self::DELIVERY, self::SUBSCRIPTION, $now));
      self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 1, $now));
    });
    $this->work($this->b)->quarantine(self::DELIVERY, 0, $commandToken);
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now));
    self::assertSame('pending', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertNotNull($this->work($this->b)->claim(self::DELIVERY, 1, self::TOKEN_B, self::TOKEN_C, $now));
  }

  #[Test]
  public function aCurrentWakeCanBeQuarantinedBeforeClaiming(): void
  {
    $this->reserve();
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, new DateTimeImmutable()));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    new WebhookTechnicalFailureSubscriber($this->work($this->b))->onFailure(new WorkerMessageFailedEvent($envelopes[0], 'webhook', new RuntimeException('Claim unavailable until retries exhausted')));
    $this->receiver->ack($envelopes[0]);
    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->b->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  public function reservingABusyWakePreservesTheActiveTechnicalCycle(): void
  {
    $this->reserve(self::TOKEN_A);
    $now = new DateTimeImmutable();
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, self::TOKEN_A, self::TOKEN_C, $now));
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now->modify('+90 seconds')));
    self::assertSame(self::TOKEN_A, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->work($this->b)->claim(self::DELIVERY, 0, self::TOKEN_B, self::TOKEN_D, $now));
    self::assertSame(self::TOKEN_A, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_D)->enqueue(self::DELIVERY, 0, $now->modify('+90 seconds')));
    self::assertSame(self::TOKEN_A, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_D, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work($this->b)->quarantine(self::DELIVERY, 0, self::TOKEN_B);
    self::assertSame(self::TOKEN_C, $this->b->fetchOne('SELECT attempt_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_C, $now);
    $this->work($this->b)->quarantine(self::DELIVERY, 0, self::TOKEN_A);
    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->b->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  public function recoveryTransfersOwnershipAfterThePreviousLeaseExpired(): void
  {
    $this->reserve(self::TOKEN_A);
    $now = new DateTimeImmutable();
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, self::TOKEN_A, self::TOKEN_C, $now));
    $this->a->executeStatement('UPDATE webhook_deliveries SET lease_until = ? WHERE id = ?', [$now->modify('-1 second'), self::DELIVERY], ['datetime_immutable']);
    self::assertCount(1, $this->work($this->b)->recoverable($now, 1000));
    self::assertTrue($this->schedule(wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->b->fetchOne('SELECT attempt_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertNull($this->b->fetchOne('SELECT lease_until FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_C, $now);
    $this->work($this->b)->quarantine(self::DELIVERY, 0, self::TOKEN_A);
    self::assertSame('pending', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(0, $this->b->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->receiver->getMessageCount());
    $this->work($this->b)->quarantine(self::DELIVERY, 0, self::TOKEN_B);
    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function finalizationTransfersOwnershipToAnAlreadyReservedBusyWake(?string $commandToken): void
  {
    $this->reserve($commandToken);
    $now = new DateTimeImmutable();
    $delivery = $this->work()->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now);
    self::assertNotNull($delivery);
    self::assertTrue($this->work()->startHttpAttempt(self::DELIVERY, 0, self::TOKEN_C));
    $delivery->beginHttpAttempt();
    self::assertTrue($this->schedule($this->b, wakeToken: self::TOKEN_B)->enqueue(self::DELIVERY, 0, $now->modify('+90 seconds')));
    self::assertSame($commandToken, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $delivery->recordRetryableFailure(503, 'Unexpected HTTP status 503.', $now->modify('+1 second'), $now);
    $this->a->transactional(function () use ($delivery, $now): void {
      self::assertTrue($this->work()->finish($delivery, 0, self::TOKEN_C));
      self::assertFalse($this->schedule()->enqueue(self::DELIVERY, 0, $now->modify('+1 second')));
    });
    $this->work($this->b)->quarantine(self::DELIVERY, 0, $commandToken);

    self::assertSame('pending', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT command_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(self::TOKEN_B, $this->b->fetchOne('SELECT wake_token FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]));
    self::assertSame(1, $this->b->fetchOne('SELECT attempts FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
  }

  /**
   * @return iterable<string, array{?string}>
   */
  public static function commandTokens(): iterable
  {
    yield 'current wake command' => [self::TOKEN_A];
    yield 'historical generation-zero command without a token' => [null];
  }

  #[Test]
  #[DataProvider('commandTokens')]
  public function anExpiredCurrentAttemptCanStillCloseItsTechnicalCycleBeforeRecoveryWins(?string $commandToken): void
  {
    $this->reserve($commandToken);
    $now = new DateTimeImmutable();
    self::assertNotNull($this->work()->claim(self::DELIVERY, 0, $commandToken, self::TOKEN_C, $now));
    $this->a->executeStatement('UPDATE webhook_deliveries SET lease_until = ? WHERE id = ?', [$now->modify('-1 second'), self::DELIVERY], ['datetime_immutable']);
    $this->work()->technicalFailure(self::DELIVERY, 0, self::TOKEN_C, $now);
    self::assertSame(1, $this->b->fetchOne('SELECT technical_failures FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    $this->work($this->b)->quarantine(self::DELIVERY, 0, $commandToken);
    self::assertSame('failed', $this->b->fetchOne('SELECT status FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertTrue($this->b->fetchOne('SELECT quarantined FROM webhook_deliveries WHERE id = ?', [self::DELIVERY]));
    self::assertCount(0, $this->work()->recoverable($now->modify('+2 hours'), 1000));
  }

  private function work(?Connection $connection = null): DoctrineWebhookDeliveryWorkAdapter
  {
    return new DoctrineWebhookDeliveryWorkAdapter($connection ?? $this->a);
  }

  private function schedule(?Connection $connection = null, ?MessageBusInterface $bus = null, string $wakeToken = self::TOKEN_A): DoctrineWebhookDeliveryScheduleAdapter
  {
    $connection ??= $this->a;

    if (null === $bus) {
      $sender = $this->transport($connection);
      $bus = $this->createStub(MessageBusInterface::class);
      $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => $sender->send($message instanceof Envelope ? $message : new Envelope($message)));
    }
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn($wakeToken);

    return new DoctrineWebhookDeliveryScheduleAdapter($connection, $this->work($connection), new MessengerWebhookDeliveryQueueAdapter($bus, $connection), $ids);
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function reserve(?string $commandToken = null): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($this->a);
    $entityManager->method('getRepository')->willReturn($this->createStub(EntityRepository::class));
    self::assertTrue(new WebhookDeliveryRepository($entityManager)->reserve(WebhookDeliveryId::fromString(self::DELIVERY), WebhookSubscriptionId::fromString(self::SUBSCRIPTION), $this->organization, 'webhook.ping', self::DELIVERY, ['id' => self::DELIVERY]));
    if (null !== $commandToken) {
      self::assertTrue($this->schedule(wakeToken: $commandToken)->enqueue(self::DELIVERY, 0, new DateTimeImmutable()));
      $envelopes = iterator_to_array($this->receiver->get());
      self::assertCount(1, $envelopes);
      $this->receiver->ack($envelopes[0]);
    }
  }

  private function clearRows(): void
  {
    $this->a->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->a->executeStatement('DELETE FROM webhook_deliveries WHERE subscription_id = ?', [self::SUBSCRIPTION]);
    $this->a->executeStatement('DELETE FROM webhook_subscriptions WHERE id = ?', [self::SUBSCRIPTION]);
  }
}
