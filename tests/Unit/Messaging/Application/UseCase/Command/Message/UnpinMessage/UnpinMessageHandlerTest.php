<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\UnpinMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Message\UnpinMessage\{UnpinMessageCommand, UnpinMessageHandler};
use Messaging\Domain\Event\Message\MessagingMessageUnpinModeratedEvent;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessageId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};

/**
 * Test UnpinMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UnpinMessageHandler::class)]
final class UnpinMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PINNER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string MANAGER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeSelfUnpinByThePinnerDoesNotDispatchAModerationEvent(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->pinnedMessage());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::PINNER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $eventDispatcher,
      $this->createStub(LoggerPort::class),
    );

    $handler->__invoke(new UnpinMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeManagerUnpinningAnotherMembersPinDispatchesAModerationEvent(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->pinnedMessage());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MANAGER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof MessagingMessageUnpinModeratedEvent
        && self::MESSAGE_ID === $event->messageId
        && self::PINNER_MEMBER_ID === $event->pinnedByMemberId));

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $eventDispatcher,
      $this->createStub(LoggerPort::class),
    );

    $handler->__invoke(new UnpinMessageCommand('manager-user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNeitherThePinnerNorAManager(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->pinnedMessage());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('other-member-1');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(EventDispatcherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new UnpinMessageCommand('other-user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeOnANonPinnedMessageIsANoOpAndNeverErrors(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->unpinnedMessage());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('any-member-1');

    // No manage permission and not the (nonexistent) pinner — must still succeed.
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::never())->method('publishMessage');

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $eventDispatcher,
      $this->createStub(LoggerPort::class),
    );

    $result = $handler->__invoke(new UnpinMessageCommand('any-user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(EventDispatcherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UnpinMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->pinnedMessage());
    $messages->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::PINNER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning')->with('Messaging realtime publish failed.');

    $handler = new UnpinMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(EventDispatcherPort::class),
      $logger,
    );

    $result = $handler->__invoke(new UnpinMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  private function pinnedMessage(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
      $now,
      self::PINNER_MEMBER_ID,
    );
  }

  private function unpinnedMessage(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }

  private function messageView(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }
}
