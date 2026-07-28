<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Conversation\GetConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationHandler, GetConversationQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetConversationHandler::class)]
final class GetConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeResolvesTheSubjectLabelAndUnreadCount(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn([self::CONVERSATION_ID => 4]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn([self::CONVERSATION_ID]);

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetConversationHandler($conversations, $readMarkers, $favorites, $registry, $accessPolicy);

    $result = $handler->__invoke(new GetConversationQuery('user-1', self::CONVERSATION_ID));

    self::assertSame('Site nord', $result->subjectLabel);
    self::assertSame(4, $result->unreadCount);
    self::assertTrue($result->isFavorite);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new GetConversationHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new GetConversationQuery('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeEnforcesChannelParticipationForAParticipantsConversation(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn([]);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $handler = new GetConversationHandler(
      $conversations,
      $readMarkers,
      $favorites,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $result = $handler->__invoke(new GetConversationQuery('user-1', self::CONVERSATION_ID));

    self::assertNull($result->subjectLabel);
    self::assertSame(0, $result->unreadCount);
    self::assertFalse($result->isFavorite);
  }

  #[Test]
  public function testInvokeRejectsANonParticipantOfAChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $handler = new GetConversationHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new GetConversationQuery('user-1', self::CONVERSATION_ID));
  }

  private function channelView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'general');
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

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 5, false, $now, $now);
  }
}
