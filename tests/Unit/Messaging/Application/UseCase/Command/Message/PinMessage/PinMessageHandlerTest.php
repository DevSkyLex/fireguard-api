<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\PinMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Message\PinMessage\{PinMessageCommand, PinMessageHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\{MessageId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Test PinMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PinMessageHandler::class)]
final class PinMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testInvokePinsAndPublishesRealtime(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with(self::ORG_ID, self::CONVERSATION_ID);

    $handler = new PinMessageHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(LoggerPort::class),
    );

    $result = $handler->__invoke(new PinMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsAlreadyDeleted(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message(deleted: true));

    $handler = new PinMessageHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new PinMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new PinMessageHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new PinMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenWritePermissionIsMissing(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new MessagingAccessDeniedException('Missing permission.'));

    $handler = new PinMessageHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new PinMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->method('save')->willReturn($this->messageView());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    $handler = new PinMessageHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(LoggerPort::class),
    );

    $result = $handler->__invoke(new PinMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function message(bool $deleted = false): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $message = Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      $deleted ? $now : null,
      $deleted ? self::AUTHOR_MEMBER_ID : null,
      $now,
      $now,
    );

    return $message;
  }

  private function messageView(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
      $now,
      self::AUTHOR_MEMBER_ID,
    );
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 1, false, $now, $now);
  }
}
