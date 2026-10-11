<?php

declare(strict_types=1);

namespace Tests\Integration\Billing\Infrastructure\Persistence\Doctrine;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Infrastructure\Persistence\Doctrine\Lock\PostgresBillingReconciliationAdapter;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use Notification\Application\Contract\Event\NotificationDeliveryRequestedEvent;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\Internal\PlanRepositoryPort;
use Organization\Application\Port\Outbound\OrganizationLogoImagePort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanHandler};
use Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo\{UploadOrganizationLogoCommand, UploadOrganizationLogoHandler};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Port\Outbound\{CurrentActorPort, UuidGeneratorPort};
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileStoragePort, LoggerPort};
use Shared\Infrastructure\Messaging\Outbox\{OutboxEvent, TransactionalEventDispatcher};
use Shared\Infrastructure\Messaging\Outbox\{RootTransactionManagerAdapter, TransactionalFileCleanupAdapter};
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function array_key_exists;
use function iterator_to_array;
use function str_repeat;

/** Proves the final PostgreSQL commit boundary with an independent transport receiver. */
final class OrganizationPlanOutboxTest extends KernelTestCase
{
  private const string ORGANIZATION = 'bd000000-0000-4000-8000-000000000081';

  private const string PLAN = 'bd000000-0000-4000-8000-000000000082';

  private const string OWNER = 'bd000000-0000-4000-8000-000000000083';

  private const string QUEUE = 'organization_plan_outbox_regression';

  private Connection $writer;

  private Connection $reader;

  private EntityManagerInterface $entityManager;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $configured */
    $configured = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->reader = DriverManager::getConnection(['url' => $url]);
    $this->entityManager = new EntityManager($this->writer, $configured->getConfiguration());
    $this->sender = $this->transport($this->writer);
    $this->receiver = $this->transport($this->reader);
    $this->sender->setup();
    $this->clearRows();
    new OrganizationRepository($this->entityManager)->save(Organization::create(new OrganizationId(self::ORGANIZATION), new OrganizationName('Transactional plan'), self::OWNER));
  }

  protected function tearDown(): void
  {
    foreach ([$this->writer, $this->reader] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    $this->entityManager->close();
    $this->writer->close();
    $this->reader->close();
    parent::tearDown();
  }

  #[Test]
  public function lateBillingReceiptFailureRollsBackThePlanAndBothDurableConsequences(): void
  {
    try {
      new PostgresBillingReconciliationAdapter($this->entityManager)->processEvent(self::ORGANIZATION, new StripeEvent(type: str_repeat('x', 129), eventId: 'evt_plan_outbox_regression'), function (): void {
        $this->handler()(new ChangeOrganizationPlanCommand(self::ORGANIZATION, self::PLAN, true));
        self::assertSame(0, $this->receiver->getMessageCount());
        self::assertNull($this->reader->fetchOne('SELECT plan_id FROM organizations WHERE id = ?', [self::ORGANIZATION]));
      });
      self::fail('The journal constraint must fail after the plan callback.');
    } catch (DriverException $exception) {
      self::assertSame('22001', $exception->getSQLState());
    }
    self::assertNull($this->reader->fetchOne('SELECT plan_id FROM organizations WHERE id = ?', [self::ORGANIZATION]));
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertSame(0, $this->reader->fetchOne('SELECT COUNT(*) FROM billing_stripe_events WHERE organization_id = ?', [self::ORGANIZATION]));
    $this->entityManager = new EntityManager($this->writer, $this->entityManager->getConfiguration());
    $event = new StripeEvent(type: 'customer.subscription.updated', eventId: 'evt_plan_outbox_regression');
    new PostgresBillingReconciliationAdapter($this->entityManager)->processEvent(self::ORGANIZATION, $event, function (): void {
      $this->handler()(new ChangeOrganizationPlanCommand(self::ORGANIZATION, self::PLAN, true));
    });
    new PostgresBillingReconciliationAdapter($this->entityManager)->processEvent(self::ORGANIZATION, $event, static function (): void {
      self::fail('A committed Billing receipt must prevent another plan assignment.');
    });
    self::assertSame(self::PLAN, $this->reader->fetchOne('SELECT plan_id FROM organizations WHERE id = ?', [self::ORGANIZATION]));
    self::assertSame(2, $this->receiver->getMessageCount());
  }

  #[Test]
  public function standaloneChangeCommitsThePlanAndTheImmutableNotificationSnapshotTogether(): void
  {
    $this->handler()(new ChangeOrganizationPlanCommand(self::ORGANIZATION, self::PLAN, true));
    self::assertSame(self::PLAN, $this->reader->fetchOne('SELECT plan_id FROM organizations WHERE id = ?', [self::ORGANIZATION]));
    self::assertSame(2, $this->receiver->getMessageCount());
    $envelopes = [...iterator_to_array($this->receiver->get()), ...iterator_to_array($this->receiver->get())];
    self::assertCount(2, $envelopes);
    $requests = [];
    foreach ($envelopes as $envelope) {
      $message = $envelope->getMessage();
      self::assertInstanceOf(OutboxEvent::class, $message);
      if ($message->event instanceof NotificationDeliveryRequestedEvent) {
        $requests[] = $message->event->request;
      }
      $this->receiver->ack($envelope);
    }
    self::assertCount(1, $requests);
    self::assertSame(self::OWNER, $requests[0]->recipientUserId);
    self::assertNull($requests[0]->recipientEmail);
    self::assertSame('Transactional plan', $requests[0]->payload['organizationName']);
    self::assertSame(['members' => ['usage' => 60, 'limit' => 50]], $requests[0]->payload['violations']);
  }

  #[Test]
  public function lateLogoFailureRollsBackMetadataAndCleanupWhilePreservingThePreviousBytes(): void
  {
    $repository = new OrganizationRepository($this->entityManager);
    $organization = $repository->findById(new OrganizationId(self::ORGANIZATION));
    self::assertNotNull($organization);
    $organization->setLogoUrl('https://app.test/api/organizations/' . self::ORGANIZATION . '/logo.webp?v=1726000000');
    $repository->save($organization);
    $oldPath = 'organization-logos/' . self::ORGANIZATION . '/logo.webp';
    $attempt = 'bd000000-0000-4000-8000-000000000086';
    $newPath = 'organization-logos/' . self::ORGANIZATION . '/' . $attempt . '.webp';
    $bytes = [$oldPath => 'original'];
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::once())->method('write')->with($newPath, 'replacement')->willReturnCallback(static function (string $path, string $contents) use (&$bytes): void { $bytes[$path] = $contents; });
    $files->method('exists')->willReturnCallback(static function (string $path) use (&$bytes): bool { return array_key_exists($path, $bytes); });
    $files->expects(self::once())->method('delete')->with($newPath)->willReturnCallback(static function (string $path) use (&$bytes): void { unset($bytes[$path]); });
    $images = $this->createStub(OrganizationLogoImagePort::class);
    $images->method('encode')->willReturn('replacement');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn($attempt);
    $outboxIds = $this->createStub(UuidGeneratorPort::class);
    $outboxIds->method('generate')->willReturn('bd000000-0000-4000-8000-000000000087');
    $dispatcher = new TransactionalEventDispatcher($this->writer, $this->sender, $outboxIds, $this->createStub(CurrentActorPort::class));
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willReturnCallback(function (): never {
      self::assertSame(0, $this->receiver->getMessageCount());

      throw new RuntimeException('Late logo event failure');
    });
    $handler = new UploadOrganizationLogoHandler($repository, $authorization, $images, $files, new TransactionalFileCleanupAdapter($dispatcher), new RootTransactionManagerAdapter($this->writer, $this->entityManager), $events, $ids, $this->createStub(LoggerPort::class));

    try {
      $handler(new UploadOrganizationLogoCommand(self::ORGANIZATION, self::OWNER, 'source', 'https://app.test'));
      self::fail('The late event failure must propagate.');
    } catch (RuntimeException $failure) {
      self::assertSame('Late logo event failure', $failure->getMessage());
    }
    self::assertSame('https://app.test/api/organizations/' . self::ORGANIZATION . '/logo.webp?v=1726000000', $this->reader->fetchOne('SELECT logo_url FROM organizations WHERE id = ?', [self::ORGANIZATION]));
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertSame([$oldPath => 'original'], $bytes);
  }

  private function handler(): ChangeOrganizationPlanHandler
  {
    $plans = $this->createStub(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn(Plan::create(new PlanId(self::PLAN), new PlanKey('outbox-regression'), 'Outbox regression', ['members' => 50]));
    $quota = $this->createStub(OrganizationQuotaPort::class);
    $quota->method('getUsage')->willReturn(60);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturnOnConsecutiveCalls('bd000000-0000-4000-8000-000000000084', 'bd000000-0000-4000-8000-000000000085');

    return new ChangeOrganizationPlanHandler(new OrganizationRepository($this->entityManager), $plans, $quota, new DoctrineTransactionManagerAdapter($this->entityManager), new TransactionalEventDispatcher($this->writer, $this->sender, $ids, $this->createStub(CurrentActorPort::class)));
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function clearRows(): void
  {
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->writer->delete('billing_stripe_events', ['organization_id' => self::ORGANIZATION]);
    $this->writer->delete('organizations', ['id' => self::ORGANIZATION]);
  }
}
