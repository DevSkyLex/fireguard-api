<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead\{MarkConversationReadCommand, MarkConversationReadHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MarkConversationReadHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MarkConversationReadHandler::class)]
final class MarkConversationReadHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeUpsertsTheReadMarkerForTheActingMember(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())
      ->method('upsert')
      ->with(self::CONVERSATION_ID, self::ORG_ID, self::MEMBER_ID, self::isInstanceOf(DateTimeImmutable::class), 'message-1');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new MarkConversationReadHandler($conversations, $readMarkers, $registry, $accessPolicy);

    $result = $handler->__invoke(new MarkConversationReadCommand('user-1', self::CONVERSATION_ID, 'message-1'));

    self::assertSame(self::CONVERSATION_ID, $result->conversation->id);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new MarkConversationReadHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new MarkConversationReadCommand('user-1', self::CONVERSATION_ID, 'message-1'));
  }

  #[Test]
  public function testInvokeEnforcesChannelParticipationForAParticipantsConversation(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())->method('upsert');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $handler = new MarkConversationReadHandler(
      $conversations,
      $readMarkers,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $result = $handler->__invoke(new MarkConversationReadCommand('user-1', self::CONVERSATION_ID, 'message-1'));

    self::assertSame(self::CONVERSATION_ID, $result->conversation->id);
  }

  #[Test]
  public function testInvokeRejectsANonParticipantOfAChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('upsert');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $handler = new MarkConversationReadHandler(
      $conversations,
      $readMarkers,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new MarkConversationReadCommand('user-1', self::CONVERSATION_ID, 'message-1'));
  }

  private function channelView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'channel', null, 'participants', null, 3, false, $now, $now, 'general');
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 3, false, $now, $now);
  }
}
