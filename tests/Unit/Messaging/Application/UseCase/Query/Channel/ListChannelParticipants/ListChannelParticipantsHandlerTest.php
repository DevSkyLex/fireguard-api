<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Channel\ListChannelParticipants;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelView, ParticipantView};
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Channel\ListChannelParticipants\{ListChannelParticipantsHandler, ListChannelParticipantsQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListChannelParticipantsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelParticipantsHandler::class)]
final class ListChannelParticipantsHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MEMBER_ID = 'member-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeReturnsTheParticipantsForAChannelParticipant(): void
  {
    $expected = $this->participants();

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);
    $participants->method('listParticipants')->willReturn($expected);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants);

    $handler = new ListChannelParticipantsHandler($conversations, $participants, $accessPolicy);

    $result = $handler->__invoke(new ListChannelParticipantsQuery(self::USER_ID, self::CONVERSATION_ID));

    self::assertSame($expected, $result->participants);
    self::assertCount(2, $result->participants);
  }

  #[Test]
  public function testInvokeReturnsTheParticipantsForAManagerWhoIsNotAParticipant(): void
  {
    $expected = $this->participants();

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    // A non-participant manager still reads the channel: `.manage` bypasses the
    // participation check, so `isParticipant` is left returning false (default).
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('listParticipants')->willReturn($expected);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $handler = new ListChannelParticipantsHandler($conversations, $participants, $accessPolicy);

    $result = $handler->__invoke(new ListChannelParticipantsQuery(self::USER_ID, self::CONVERSATION_ID));

    self::assertSame($expected, $result->participants);
  }

  #[Test]
  public function testInvokeThrowsWhenTheChannelIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn(null);

    $handler = new ListChannelParticipantsHandler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListChannelParticipantsQuery(self::USER_ID, self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserIsNotAnActiveMember(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListChannelParticipantsHandler($conversations, $this->createStub(MessagingParticipantRepositoryPort::class), $accessPolicy);

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListChannelParticipantsQuery(self::USER_ID, self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserIsNeitherAParticipantNorAManager(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants);

    $handler = new ListChannelParticipantsHandler($conversations, $participants, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListChannelParticipantsQuery(self::USER_ID, self::CONVERSATION_ID));
  }

  /**
   * @return list<ParticipantView>
   */
  private function participants(): array
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return [
      new ParticipantView(self::CONVERSATION_ID, self::MEMBER_ID, 'owner', 'manual', $now),
      new ParticipantView(self::CONVERSATION_ID, 'member-2', null, 'team', $now),
    ];
  }

  private function channel(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CONVERSATION_ID, self::ORG_ID, 'General', null, self::MEMBER_ID, 2, false, null, 5, $now, $now);
  }
}
