<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation\{UnfavoriteConversationCommand, UnfavoriteConversationHandler};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test UnfavoriteConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UnfavoriteConversationHandler::class)]
final class UnfavoriteConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeUnfavoritesTheConversation(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    /** @var MessagingConversationFavoriteRepositoryPort&MockObject $favorites */
    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::once())
      ->method('unfavorite')
      ->with(self::CONVERSATION_ID, self::MEMBER_ID);

    $handler = new UnfavoriteConversationHandler(
      $conversations,
      $favorites,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $result = $handler->__invoke(new UnfavoriteConversationCommand('user-1', self::CONVERSATION_ID));

    self::assertSame(self::CONVERSATION_ID, $result->conversation->id);
  }

  #[Test]
  public function testInvokeIsIdempotentWhenNeverFavorited(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);

    $handler = new UnfavoriteConversationHandler(
      $conversations,
      $favorites,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $handler->__invoke(new UnfavoriteConversationCommand('user-1', self::CONVERSATION_ID));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new UnfavoriteConversationHandler(
      $conversations,
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UnfavoriteConversationCommand('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNotAnActiveMember(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new UnfavoriteConversationHandler(
      $conversations,
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UnfavoriteConversationCommand('user-1', self::CONVERSATION_ID));
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 1, false, $now, $now);
  }
}
