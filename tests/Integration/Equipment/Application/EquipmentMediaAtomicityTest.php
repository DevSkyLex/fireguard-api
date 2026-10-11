<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Application;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use Equipment\Application\Service\EquipmentMediaAccess;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentHandler};
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\{DeleteAttachmentCommand, DeleteAttachmentHandler};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\{AttachmentRepository, CanonicalEquipmentRepository, EquipmentMediaLockRepository, EquipmentMediaReadRepository, EquipmentRepository};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{CurrentActorPort, DurableEventContextPort, FileStoragePort, LoggerPort, UuidGeneratorPort};
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Throwable;

use function array_values;
use function hash;
use function iterator_to_array;

/**
 * Verifies final commits and durable cleanup through independent PostgreSQL connections.
 *
 * @category Integration Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentMediaAtomicityTest extends KernelTestCase
{
  private const string ORGANIZATION = '87f08400-e29b-41d4-a716-446655480011';

  private const string EQUIPMENT = '87f08400-e29b-41d4-a716-446655480012';

  private const string ATTACHMENT = '87f08400-e29b-41d4-a716-446655480013';

  private const string EVENT = '87f08400-e29b-41d4-a716-446655480014';

  private const string QUEUE = 'equipment_media_atomicity';

  private Connection $writer;

  private Connection $observer;

  private EntityManagerInterface $manager;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  private FileStoragePort $storage;

  /**
   * @var array<string, string>
   */
  private array $blobs = [];

  private int $deletions = 0;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    /** @var EntityManagerInterface $template */
    $template = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->manager = new EntityManager($this->writer, $template->getConfiguration(), new EventManager());
    $this->sender = $this->transport($this->writer);
    $this->receiver = $this->transport($this->observer);
    $this->sender->setup();
    $this->clearRows();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Media atomicity';
    $organization->slug = 'media-atomicity-' . self::ORGANIZATION;
    $organization->ownerUserId = self::ORGANIZATION;
    $organization->createdByUserId = self::ORGANIZATION;
    $organization->createdAt = $organization->updatedAt = new DateTimeImmutable();
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organizationId = self::ORGANIZATION;
    $equipment->type = 'fire_extinguisher';
    $equipment->createdAt = $equipment->updatedAt = new DateTimeImmutable();
    $equipment->tagLinks = new ArrayCollection();
    $equipment->attachments = new ArrayCollection();
    $this->manager->persist($organization);
    $this->manager->persist($equipment);
    $this->manager->flush();
    $this->storage = $this->createStub(FileStoragePort::class);
    $this->storage->method('write')->willReturnCallback(function (string $path, string $bytes): void { $this->blobs[$path] = $bytes; });
    $this->storage->method('delete')->willReturnCallback(function (string $path): void {
      unset($this->blobs[$path]);
      ++$this->deletions;
    });
  }

  protected function tearDown(): void
  {
    if ($this->writer->isTransactionActive()) {
      $this->writer->rollBack();
    }
    $this->manager->clear();
    $this->clearRows();
    $this->writer->close();
    $this->observer->close();
    parent::tearDown();
  }

  #[Test]
  public function aLateSqlRevisionFailureRollsBackMetadataAndCompensatesOnlyTheAttempt(): void
  {
    try {
      $this->upload($this->interventions(true))($this->command());
      self::fail('The late SQL failure must propagate.');
    } catch (Throwable $failure) {
      self::assertStringContainsString('revision', $failure->getPrevious()?->getMessage() ?? $failure->getMessage());
    }
    self::assertSame([], $this->blobs);
    self::assertSame(1, $this->deletions);
    self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE id = ?', [self::ATTACHMENT]));
    self::assertSame(1, $this->observer->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT]));
    self::assertSame(0, $this->receiver->getMessageCount());
  }

  #[Test]
  public function deletionRollbackPreservesBytesThenCommittedCleanupIsDeliveredOnce(): void
  {
    $this->upload($this->interventions())($this->command());
    self::assertCount(1, $this->blobs);

    try {
      $this->delete($this->interventions(true))(new DeleteAttachmentCommand(self::ORGANIZATION, self::EQUIPMENT, self::ATTACHMENT, expectedRevision: 1));
      self::fail('The late deletion failure must propagate.');
    } catch (Throwable) {
      self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE id = ?', [self::ATTACHMENT]));
    }
    self::assertCount(1, $this->blobs);
    self::assertSame(0, $this->deletions);
    self::assertSame(0, $this->receiver->getMessageCount());
    $this->delete($this->interventions())(new DeleteAttachmentCommand(self::ORGANIZATION, self::EQUIPMENT, self::ATTACHMENT, expectedRevision: 1));
    self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE id = ?', [self::ATTACHMENT]));
    self::assertCount(1, $this->blobs);
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(FileCleanupRequested::class, $message->event);
    $context = $this->createStub(DurableEventContextPort::class);
    $context->method('eventId')->willReturn($message->id);
    $consumer = new FileCleanupSubscriber($context, new DoctrineIdempotentConsumerAdapter($this->observer), $this->storage);
    $consumer->onCleanup($message->event);
    $consumer->onCleanup($message->event);
    self::assertSame([], $this->blobs);
    self::assertSame(1, $this->deletions);
    $this->receiver->ack($envelopes[0]);
  }

  #[Test]
  public function aCommittedReplayKeepsTheWinningMetadataAndBytes(): void
  {
    $handler = $this->upload($this->interventions());
    $original = $handler($this->command());
    $replay = $handler(new AddAttachmentCommand(self::ORGANIZATION, self::EQUIPMENT, 'other.pdf', 'other', 'application/pdf', 5, attachmentId: self::ATTACHMENT));
    self::assertSame($original->fileName, $replay->fileName);
    self::assertSame(['bytes'], array_values($this->blobs));
    self::assertSame(0, $this->deletions);
  }

  #[Test]
  public function anActualEnclosingTransactionIsRejectedBeforeWritingBytes(): void
  {
    $this->writer->beginTransaction();

    try {
      $this->upload($this->interventions())($this->command());
      self::fail('An enclosing transaction must be rejected.');
    } catch (TransactionAlreadyActiveException) {
      self::assertSame([], $this->blobs);
      self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE id = ?', [self::ATTACHMENT]));
    } finally {
      $this->writer->rollBack();
    }
  }

  private function command(): AddAttachmentCommand
  {
    return new AddAttachmentCommand(self::ORGANIZATION, self::EQUIPMENT, 'original.pdf', 'bytes', 'application/pdf', 5, attachmentId: self::ATTACHMENT);
  }

  private function interventions(bool $fail = false): InterventionResourcesPort
  {
    $port = $this->createStub(InterventionResourcesPort::class);
    if ($fail) {
      $port->method('touchDraftIntervention')->willReturnCallback(function (): void {
        $this->writer->executeStatement('UPDATE equipment SET revision = NULL WHERE id = ?', [self::EQUIPMENT]);
      });
    }

    return $port;
  }

  private function cleanup(): TransactionalFileCleanupAdapter
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::EVENT);

    return new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($this->writer, $this->sender, $ids, $this->createStub(CurrentActorPort::class)));
  }

  private function upload(InterventionResourcesPort $interventions): AddAttachmentHandler
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::EVENT);

    return new AddAttachmentHandler(
      new EquipmentRepository($this->manager),
      new AttachmentRepository($this->manager),
      $this->storage,
      $ids,
      new RootTransactionManagerAdapter($this->writer, $this->manager),
      new EquipmentMediaLockRepository($this->manager),
      new CanonicalEquipmentRepository($this->manager),
      new EquipmentMediaReadRepository($this->manager),
      new EquipmentMediaAccess($this->createStub(OrganizationAuthorizationPort::class), $interventions),
      $interventions,
      $this->cleanup(),
      $this->createStub(LoggerPort::class),
    );
  }

  private function delete(InterventionResourcesPort $interventions): DeleteAttachmentHandler
  {
    return new DeleteAttachmentHandler(
      new EquipmentRepository($this->manager),
      new AttachmentRepository($this->manager),
      new RootTransactionManagerAdapter($this->writer, $this->manager),
      new EquipmentMediaLockRepository($this->manager),
      new EquipmentMediaReadRepository($this->manager),
      new EquipmentMediaAccess($this->createStub(OrganizationAuthorizationPort::class), $interventions),
      $interventions,
      $this->cleanup(),
    );
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
    $this->writer->executeStatement('DELETE FROM equipment_attachments WHERE equipment_id = ?', [self::EQUIPMENT]);
    $this->writer->executeStatement('DELETE FROM equipment WHERE id = ?', [self::EQUIPMENT]);
    $this->writer->executeStatement('DELETE FROM organizations WHERE id = ?', [self::ORGANIZATION]);
    $this->writer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . "\0" . FileCleanupSubscriber::class)]);
  }
}
