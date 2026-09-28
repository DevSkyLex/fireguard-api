<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery\{AcknowledgeDeliveryCommand, AcknowledgeDeliveryHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException, MessagingValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

#[CoversClass(AcknowledgeDeliveryHandler::class)]
final class AcknowledgeDeliveryHandlerTest extends TestCase
{
  #[Test]
  public function testAcknowledgesAnIncomingMessageAndPublishesAReceiptInvalidation(): void
  {
    $markers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $markers->expects(self::once())->method('markDelivered')->with('conversation-1', 'org-1', 'member-1', 'message-1', self::isInstanceOf(DateTimeImmutable::class));
    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with('org-1', 'conversation-1', ['type' => 'receipt.changed', 'memberId' => 'member-1']);

    $handler = $this->handler($markers, $realtime, $this->message('member-2'), true);
    self::assertSame('message-1', $handler(new AcknowledgeDeliveryCommand('user-1', 'conversation-1', 'message-1'))->messageId);
  }

  #[Test]
  public function testRejectsAcknowledgingOwnMessage(): void
  {
    $markers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $markers->expects(self::never())->method('markDelivered');
    $handler = $this->handler($markers, $this->createStub(MessagingRealtimePublisherPort::class), $this->message('member-1'), true);

    $this->expectException(MessagingValidationException::class);
    $handler(new AcknowledgeDeliveryCommand('user-1', 'conversation-1', 'message-1'));
  }

  #[Test]
  public function testRejectsAnotherConversationsMessage(): void
  {
    $handler = $this->handler($this->createStub(MessagingReadMarkerRepositoryPort::class), $this->createStub(MessagingRealtimePublisherPort::class), $this->message('member-2', 'conversation-2'), true);

    $this->expectException(MessagingNotFoundException::class);
    $handler(new AcknowledgeDeliveryCommand('user-1', 'conversation-1', 'message-1'));
  }

  #[Test]
  public function testRejectsAReaderOutsideTheConversation(): void
  {
    $markers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $markers->expects(self::never())->method('markDelivered');
    $handler = $this->handler($markers, $this->createStub(MessagingRealtimePublisherPort::class), $this->message('member-2'), false);

    $this->expectException(MessagingAccessDeniedException::class);
    $handler(new AcknowledgeDeliveryCommand('user-1', 'conversation-1', 'message-1'));
  }

  private function handler(MessagingReadMarkerRepositoryPort $markers, MessagingRealtimePublisherPort $realtime, MessageView $message, bool $participant): AcknowledgeDeliveryHandler
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $conversations->method('findById')->willReturn(new ConversationView('conversation-1', 'org-1', 'direct', null, 'participants', null, 1, false, $at, $at));
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn($participant);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    return new AcknowledgeDeliveryHandler($conversations, $messages, $markers, $realtime, new MessagingAccessPolicy($authorization, $members, $participants), $this->createStub(LoggerPort::class));
  }

  private function message(string $author, string $conversationId = 'conversation-1'): MessageView
  {
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView('message-1', $conversationId, 'org-1', $author, 'Hello', [], null, null, null, $at, $at);
  }
}
