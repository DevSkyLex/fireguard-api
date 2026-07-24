<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\ArchiveConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Conversation\ArchiveConversation\{ArchiveConversationCommand, ArchiveConversationHandler};
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ConversationId, ConversationVisibility, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test ArchiveConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ArchiveConversationHandler::class)]
final class ArchiveConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeArchivesAndDispatchesTheArchivedEvent(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());
    $conversations->expects(self::once())->method('save')->willReturn($this->view(isArchived: true));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions');
    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof MessagingConversationArchivedEvent
        && self::CONVERSATION_ID === $event->conversationId));

    $handler = new ArchiveConversationHandler($conversations, $accessPolicy, $eventDispatcher);

    $result = $handler->__invoke(new ArchiveConversationCommand('user-1', self::CONVERSATION_ID, true));

    self::assertTrue($result->conversation->isArchived);
  }

  #[Test]
  public function testInvokeIsIdempotentAndDoesNotRedispatchWhenAlreadyArchived(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation(archived: true));
    $conversations->expects(self::once())->method('save')->willReturn($this->view(isArchived: true));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new ArchiveConversationHandler($conversations, $accessPolicy, $eventDispatcher);

    $handler->__invoke(new ArchiveConversationCommand('user-1', self::CONVERSATION_ID, true));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationDoesNotExist(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn(null);

    $handler = new ArchiveConversationHandler(
      $conversations,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ArchiveConversationCommand('user-1', self::CONVERSATION_ID, true));
  }

  #[Test]
  public function testInvokeThrowsWhenManagePermissionIsMissing(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new ArchiveConversationHandler(
      $conversations,
      new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new ArchiveConversationCommand('user-1', self::CONVERSATION_ID, true));
  }

  private function conversation(bool $archived = false): Conversation
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
      ConversationVisibility::SUBJECT,
      null,
      0,
      $archived,
      $now,
      $now,
    );
  }

  private function view(bool $isArchived): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      self::ORG_ID,
      'facility',
      'facility-1',
      'subject',
      null,
      0,
      $isArchived,
      $now,
      $now,
    );
  }
}
