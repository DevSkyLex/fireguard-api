<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Application\UseCase;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManager, EntityManagerInterface, ORMSetup};
use Doctrine\Persistence\ConnectionRegistry;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\Internal\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Port\Outbound\{MessagingLinkRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentHandler};
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\{DeleteMessageAttachmentCommand, DeleteMessageAttachmentHandler};
use Messaging\Application\UseCase\Command\Message\PostMessage\{PostMessageCommand, PostMessageHandler};
use Messaging\Application\UseCase\Command\Message\PostReply\{PostReplyCommand, PostReplyHandler};
use Messaging\Domain\Exception\MessagingClientMessageAlreadyExistsException;
use Messaging\Domain\Model\Attachment\{MessagingAttachment, MessagingAttachmentFile};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\{MentionExtractor, UrlExtractor};
use Messaging\Domain\ValueObject\{MessageId, MessagingAttachmentId};
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingAttachmentRepository;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingConversationRepository, MessagingLinkRepository, MessagingMessageRepository, MessagingParticipantRepository};
use Notification\Application\Contract\Notification\SentNotification;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Notification\OrganizationNotificationSettings;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{CurrentActorPort, FileCleanupPort, FileStoragePort, LoggerPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\{OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Throwable;

use function dirname;
use function iterator_to_array;

/**
 * Class MessageWriteAtomicityTest
 *
 * Injects PostgreSQL failures after flushed writes and observes rollback from a separate connection.
 * These connections have no DAMA outer transaction.
 *
 * @category Integration Tests
 */
final class MessageWriteAtomicityTest extends TestCase
{
  private const string CONVERSATION = 'bc010000-0000-4000-8000-000000000001';

  private const string MESSAGE = 'bc010000-0000-4000-8000-000000000002';

  private const string PARENT = 'bc010000-0000-4000-8000-000000000003';

  private const string AUTHOR = 'bc010000-0000-4000-8000-000000000004';

  private const string MENTIONED = 'bc010000-0000-4000-8000-000000000005';

  private Connection $writer;

  private Connection $observer;

  private EntityManagerInterface $entityManager;

  private string $organizationId;

  protected function setUp(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $configuration = ORMSetup::createAttributeMetadataConfig(
      [dirname(__DIR__, 5) . '/src/Messaging/Infrastructure/Persistence/Doctrine/Record'],
      true,
    );
    $configuration->enableNativeLazyObjects(true);
    $this->entityManager = new EntityManager($this->writer, $configuration);
    $organizationId = $this->writer->fetchOne('SELECT id FROM organizations ORDER BY id LIMIT 1');
    self::assertIsString($organizationId);
    $this->organizationId = $organizationId;
    $this->transport($this->writer)->setup();
    $this->writer->executeStatement('DELETE FROM messaging_conversations WHERE id = ?', [self::CONVERSATION]);
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', ['messaging_cleanup_atomicity']);
    $this->writer->executeStatement(
      "INSERT INTO messaging_conversations (id, organization_id, subject_type, visibility, messages_count, is_archived, created_at, updated_at) VALUES (?, ?, 'facility', 'subject', 0, false, NOW(), NOW())",
      [self::CONVERSATION, $this->organizationId],
    );
  }

  protected function tearDown(): void
  {
    while ($this->writer->isTransactionActive()) {
      $this->writer->rollBack();
    }
    $this->writer->executeStatement('DELETE FROM messaging_conversations WHERE id = ?', [self::CONVERSATION]);
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', ['messaging_cleanup_atomicity']);
    $this->entityManager->close();
    $this->writer->close();
    $this->observer->close();
    parent::tearDown();
  }

  /**
   * @return iterable<string, array{bool, string}>
   */
  public static function failingStages(): iterable
  {
    yield 'root after append' => [false, 'append'];
    yield 'root after conversation count' => [false, 'conversation'];
    yield 'root after links' => [false, 'links'];
    yield 'root after participant lookup' => [false, 'participants'];
    yield 'reply after append' => [true, 'append'];
    yield 'reply after conversation count' => [true, 'conversation'];
    yield 'reply after parent count' => [true, 'parent'];
  }

  #[Test]
  #[DataProvider('failingStages')]
  public function rollsBackEveryWriteAndPublishesOnlyAfterASuccessfulRetry(bool $reply, string $stage): void
  {
    $uuid = $this->createStub(UuidGeneratorPort::class);
    $uuid->method('generate')->willReturn(self::MESSAGE);
    $realMessages = new MessagingMessageRepository($this->entityManager);
    $realConversations = new MessagingConversationRepository($this->entityManager, $uuid);
    $realLinks = new MessagingLinkRepository($this->entityManager, $uuid);
    $realParticipants = new MessagingParticipantRepository($this->entityManager);
    $isChannel = 'participants' === $stage;
    if ($isChannel) {
      $this->writer->executeStatement("UPDATE messaging_conversations SET subject_type = 'channel', visibility = 'participants' WHERE id = ?", [self::CONVERSATION]);
      $realParticipants->addParticipant(self::CONVERSATION, $this->organizationId, self::AUTHOR, null, 'manual', new DateTimeImmutable());
      $realParticipants->addParticipant(self::CONVERSATION, $this->organizationId, self::MENTIONED, null, 'manual', new DateTimeImmutable());
    }
    if ($reply) {
      $realMessages->append(Message::create(MessageId::fromString(self::PARENT), self::CONVERSATION, $this->organizationId, self::AUTHOR, 'Parent', new MentionExtractor()));
    }
    $failureState = new class () {
      public bool $enabled = true;
    };
    $inject = function (string $current) use ($stage, $failureState): void {
      if ($failureState->enabled && $stage === $current) {
        $this->writer->executeQuery('SELECT 1 / 0')->fetchOne();
      }
    };
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturnCallback($realMessages->findById(...));
    $messages->method('findAggregateById')->willReturnCallback($realMessages->findAggregateById(...));
    $messages->method('append')->willReturnCallback(static function (Message $message) use ($realMessages, $inject): MessageView {
      $view = $realMessages->append($message);
      $inject('append');

      return $view;
    });
    $messages->method('incrementReplyCount')->willReturnCallback(static function (string $id) use ($realMessages, $inject): void {
      $realMessages->incrementReplyCount($id);
      $inject('parent');
    });
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnCallback($realConversations->findAggregateById(...));
    $conversations->method('touchOnNewMessage')->willReturnCallback(static function (string $id, DateTimeImmutable $at) use ($realConversations, $inject): void {
      $realConversations->touchOnNewMessage($id, $at);
      $inject('conversation');
    });
    $links = $this->createStub(MessagingLinkRepositoryPort::class);
    $replaceLinks = static function (string $id, string $conversation, array $urls, DateTimeImmutable $at) use ($realLinks, $inject): void {
      /** @var list<string> $urls Extracted URL list supplied by the repository port callback. */
      $realLinks->replaceForMessage($id, $conversation, $urls, $at);
      $inject('links');
    };
    $links->method('replaceForMessage')->willReturnCallback($replaceLinks);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR);
    $members->method('memberIsActive')->willReturn(true);
    $members->method('resolveUserIdForMember')->willReturn('recipient');
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturnCallback($realParticipants->isParticipant(...));
    $participants->method('listMemberIds')->willReturnCallback(static function (string $conversationId) use ($realParticipants, $inject): array {
      $memberIds = $realParticipants->listMemberIds($conversationId);
      $inject('participants');

      return $memberIds;
    });
    $access = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants);
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());
    $notifications = $this->createMock(NotificationPort::class);
    $notificationAfterCommit = false;
    $realtimeAfterCommit = false;
    $notifications->expects(self::once())->method('send')->willReturnCallback(function () use (&$notificationAfterCommit): SentNotification {
      $notificationAfterCommit = !$this->writer->isTransactionActive()
        && 1 === $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE id = ?', [self::MESSAGE]);

      return new SentNotification('notification', 'mention', 'subject', 'body', [], [], [], new DateTimeImmutable());
    });
    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->willReturnCallback(function () use (&$realtimeAfterCommit): void {
      $realtimeAfterCommit = !$this->writer->isTransactionActive()
        && 1 === $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE id = ?', [self::MESSAGE]);
    });
    $notificationService = new MessagingNotificationService($notifications, $members, $policy);
    $resolver = new MessagingSubjectResolverRegistry([]);
    $logger = $this->createStub(LoggerPort::class);
    $transactions = new RootTransactionManagerAdapter($this->writer);
    $handler = $reply
      ? new PostReplyHandler($conversations, $messages, $resolver, $access, $notificationService, $realtime, new MentionExtractor(), $uuid, $logger, $transactions)
      : new PostMessageHandler($conversations, $messages, $participants, $links, $resolver, $access, $notificationService, $realtime, new MentionExtractor(), new UrlExtractor(), $uuid, $logger, $transactions);
    $body = $isChannel ? 'https://example.test' : 'https://example.test @{' . self::MENTIONED . '}';
    $command = $reply ? new PostReplyCommand('actor', self::PARENT, $body) : new PostMessageCommand('actor', self::CONVERSATION, $body, clientId: self::MESSAGE);

    try {
      $handler($command);
      self::fail('Injected SQL failure must propagate.');
    } catch (Throwable $failure) {
      self::assertStringContainsString('division by zero', $failure->getMessage());
    }
    self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE id = ?', [self::MESSAGE]));
    self::assertSame(0, $this->observer->fetchOne('SELECT messages_count FROM messaging_conversations WHERE id = ?', [self::CONVERSATION]));
    self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_message_link WHERE conversation_id = ?', [self::CONVERSATION]));
    if ($reply) {
      self::assertSame(0, $this->observer->fetchOne('SELECT reply_count FROM messaging_messages WHERE id = ?', [self::PARENT]));
    }
    $failureState->enabled = false;
    $this->entityManager->clear();
    $handler($command);
    self::assertTrue($realtimeAfterCommit);
    self::assertTrue($notificationAfterCommit);
    self::assertSame(1, $this->observer->fetchOne('SELECT messages_count FROM messaging_conversations WHERE id = ?', [self::CONVERSATION]));
    if ($reply) {
      self::assertSame(1, $this->observer->fetchOne('SELECT reply_count FROM messaging_messages WHERE id = ?', [self::PARENT]));
    } else {
      try {
        $handler($command);
        self::fail('A committed client identifier must conflict without republishing.');
      } catch (MessagingClientMessageAlreadyExistsException) {
        self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE id = ?', [self::MESSAGE]));
      }
    }
  }

  #[Test]
  public function deletionCommitsItsCleanupTogetherAndRollsBackBothOnEnqueueFailure(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $messages->append(Message::create(MessageId::fromString(self::MESSAGE), self::CONVERSATION, $this->organizationId, self::AUTHOR, 'Attachment parent', new MentionExtractor()));
    $attachments = new MessagingAttachmentRepository($this->entityManager);
    $attachmentId = MessagingAttachmentId::fromString('bc010000-0000-4000-8000-000000000006');
    $attachments->save(MessagingAttachment::create($attachmentId, self::MESSAGE, self::CONVERSATION, $this->organizationId, self::AUTHOR, new MessagingAttachmentFile('file.pdf', 'messaging/atomicity/file.pdf', 'application/pdf', 32)));
    $sender = $this->transport($this->writer);
    $receiver = $this->transport($this->observer);
    $sender->setup();
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn('bc010000-0000-4000-8000-000000000007');
    $failureState = new class () {
      public bool $enabled = true;
    };
    $faultingSender = $this->createMock(SenderInterface::class);
    $faultingSender->expects(self::exactly(2))->method('send')->willReturnCallback(function (Envelope $envelope) use ($sender, $failureState): Envelope {
      $sent = $sender->send($envelope);
      if ($failureState->enabled) {
        $this->writer->executeQuery('SELECT 1 / 0')->fetchOne();
      }

      return $sent;
    });
    $cleanup = new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($this->writer, $faultingSender, $ids, $this->createStub(CurrentActorPort::class)));
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR);
    $access = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));
    $handler = new DeleteMessageAttachmentHandler($attachments, $access, $cleanup, new RootTransactionManagerAdapter($this->writer));
    $command = new DeleteMessageAttachmentCommand('actor', (string) $attachmentId, '"revision-1"');

    try {
      $handler($command);
      self::fail('Enqueue interruption must propagate.');
    } catch (Throwable $failure) {
      self::assertStringContainsString('division by zero', $failure->getMessage());
    }
    self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_attachments WHERE id = ?', [(string) $attachmentId]));
    self::assertSame(0, $receiver->getMessageCount());
    $failureState->enabled = false;
    $this->entityManager->clear();
    $handler($command);
    self::assertSame(0, $this->observer->fetchOne('SELECT COUNT(*) FROM messaging_attachments WHERE id = ?', [(string) $attachmentId]));
    self::assertSame(1, $receiver->getMessageCount());
    $envelopes = iterator_to_array($receiver->get());
    self::assertCount(1, $envelopes);
    $event = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $event);
    self::assertInstanceOf(FileCleanupRequested::class, $event->event);
    self::assertSame('messaging/atomicity/file.pdf', $event->event->storagePath);
    $receiver->ack($envelopes[0]);
  }

  #[Test]
  public function uploadsRejectAnActualEnclosingTransactionBeforeWritingBytes(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $access = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));
    $handler = new AddMessageAttachmentHandler(
      $this->createStub(MessagingMessageRepositoryPort::class),
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      $access,
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      $storage,
      $this->createStub(UuidGeneratorPort::class),
      new RootTransactionManagerAdapter($this->writer),
      $this->createStub(FileCleanupPort::class),
      $this->createStub(LoggerPort::class),
    );
    $this->writer->beginTransaction();
    $this->expectException(TransactionAlreadyActiveException::class);
    $handler(new AddMessageAttachmentCommand('actor', self::MESSAGE, 'file.pdf', 'bytes', 'application/pdf', 5));
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport(
      'doctrine://main?queue_name=messaging_cleanup_atomicity&auto_setup=false',
      ['use_notify' => false],
      new PhpSerializer(),
    );
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }
}
