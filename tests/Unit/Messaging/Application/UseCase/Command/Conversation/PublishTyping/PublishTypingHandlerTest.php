<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Conversation\PublishTyping\{PublishTypingCommand, PublishTypingHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

#[CoversClass(PublishTypingHandler::class)]
final class PublishTypingHandlerTest extends TestCase
{
  #[Test]
  public function testPublishesOnlyIdentityAndActivityOnThePrivateConversationTopic(): void
  {
    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with('org-1', 'conversation-1', ['type' => 'typing.changed', 'memberId' => 'member-1', 'active' => true]);

    $result = ($this->handler($realtime, true, false))(new PublishTypingCommand('user-1', 'conversation-1', true));
    self::assertSame('member-1', $result->memberId);
  }

  #[Test]
  public function testRejectsNonparticipants(): void
  {
    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::never())->method('publishMessage');
    $this->expectException(MessagingAccessDeniedException::class);
    ($this->handler($realtime, false, false))(new PublishTypingCommand('user-1', 'conversation-1', true));
  }

  #[Test]
  public function testRejectsArchivedConversation(): void
  {
    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::never())->method('publishMessage');
    $this->expectException(MessagingValidationException::class);
    ($this->handler($realtime, true, true))(new PublishTypingCommand('user-1', 'conversation-1', true));
  }

  private function handler(MessagingRealtimePublisherPort $realtime, bool $participant, bool $archived): PublishTypingHandler
  {
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(new ConversationView('conversation-1', 'org-1', 'direct', null, 'participants', null, 1, $archived, $at, $at));
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn($participant);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    return new PublishTypingHandler($conversations, $realtime, new MessagingAccessPolicy($authorization, $members, $participants), $this->createStub(LoggerPort::class));
  }
}
