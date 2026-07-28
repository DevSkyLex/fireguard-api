<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\DeleteMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Message\DeleteMessage\{DeleteMessageCommand, DeleteMessageHandler};
use Messaging\Domain\Event\Message\MessagingMessageModeratedEvent;
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
 * Test DeleteMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteMessageHandler::class)]
final class DeleteMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string MANAGER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeSelfDeleteByAuthorTombstonesAndDoesNotDispatchAModerationEvent(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $eventDispatcher,
      $this->createStub(LoggerPort::class),
    );

    $handler->__invoke(new DeleteMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeManagerModeratingAnotherMembersMessageDispatchesAModerationEvent(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MANAGER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof MessagingMessageModeratedEvent
        && self::MESSAGE_ID === $event->messageId
        && self::AUTHOR_MEMBER_ID === $event->authorMemberId));

    $handler = new DeleteMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $eventDispatcher,
      $this->createStub(LoggerPort::class),
    );

    $handler->__invoke(new DeleteMessageCommand('manager-user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNeitherAuthorNorManager(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('other-member-1');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $handler = new DeleteMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(EventDispatcherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new DeleteMessageCommand('other-user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new DeleteMessageHandler(
      $messages,
      new MessagingAccessPolicy(
        $this->createStub(OrganizationAuthorizationPort::class),
        $this->createStub(MessagingMemberDirectoryPort::class),
        $this->createStub(MessagingParticipantRepositoryPort::class),
      ),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(EventDispatcherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new DeleteMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->method('save')->willReturn($this->messageView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning')->with('Messaging realtime publish failed.');

    $handler = new DeleteMessageHandler(
      $messages,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(EventDispatcherPort::class),
      $logger,
    );

    $result = $handler->__invoke(new DeleteMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  private function message(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      'conversation-1',
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
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
      'conversation-1',
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      $now,
      self::AUTHOR_MEMBER_ID,
      $now,
      $now,
    );
  }
}
