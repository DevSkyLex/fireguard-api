<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\ReadMarker\ConversationReceiptPosition;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts\{GetConversationReceiptsHandler, GetConversationReceiptsQuery};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(GetConversationReceiptsHandler::class)]
final class GetConversationReceiptsHandlerTest extends TestCase
{
  #[Test]
  public function testQueriesOnlyTheCurrentParticipants(): void
  {
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $position = new ConversationReceiptPosition('member-2', 'message-1', $at, null, null);
    $markers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $markers->expects(self::once())->method('receiptPositions')->with('conversation-1', ['member-1', 'member-2'])->willReturn([$position]);

    self::assertSame([$position], ($this->handler($markers, true))(new GetConversationReceiptsQuery('user-1', 'conversation-1'))->positions);
  }

  #[Test]
  public function testRejectsAReaderOutsideTheConversation(): void
  {
    $markers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $markers->expects(self::never())->method('receiptPositions');
    $this->expectException(MessagingAccessDeniedException::class);
    ($this->handler($markers, false))(new GetConversationReceiptsQuery('user-1', 'conversation-1'));
  }

  private function handler(MessagingReadMarkerRepositoryPort $markers, bool $participant): GetConversationReceiptsHandler
  {
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(new ConversationView('conversation-1', 'org-1', 'direct', null, 'participants', null, 1, false, $at, $at));
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn($participant);
    $participants->method('listMemberIds')->willReturn(['member-1', 'member-2']);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    return new GetConversationReceiptsHandler($conversations, $participants, $markers, new MessagingAccessPolicy($authorization, $members, $participants));
  }
}
