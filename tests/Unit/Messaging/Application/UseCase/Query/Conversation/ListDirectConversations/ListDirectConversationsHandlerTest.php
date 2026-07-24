<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Conversation\ListDirectConversations;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Conversation\ListDirectConversations\{ListDirectConversationsHandler, ListDirectConversationsQuery};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListDirectConversationsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListDirectConversationsHandler::class)]
final class ListDirectConversationsHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string MEMBER_ID = 'member-1';

  /**
   * Asserts the handler resolves the acting member id and forwards it, plus
   * the `isArchived` filter and pagination, to
   * `listDirectConversationsForMember()` (participant scoping/ordering
   * themselves are the repository's concern, verified byte-for-byte against
   * `listChannelsForMember()` by code inspection — see MODULE.md), then
   * enriches the page with counterpart member ids, unread counts, and
   * favorites.
   */
  #[Test]
  public function testInvokeScopesToTheActingMemberAndEnrichesThePage(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new ConversationView('direct-1', self::ORG_ID, 'direct', 'pair-key', 'participants', $now, 4, false, $now, $now);
    $page = new ConversationPage([$view], 2, 10, 1);

    /** @var MessagingConversationRepositoryPort&MockObject $conversations */
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::once())
      ->method('listDirectConversationsForMember')
      ->with(self::ORG_ID, self::MEMBER_ID, true, 2, 10)
      ->willReturn($page);

    /** @var MessagingParticipantRepositoryPort&MockObject $participants */
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())
      ->method('findCounterpartMemberIds')
      ->with(['direct-1'], self::MEMBER_ID)
      ->willReturn(['direct-1' => 'counterpart-member-1']);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn(['direct-1' => 3]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn(['direct-1']);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListDirectConversationsHandler($conversations, $participants, $readMarkers, $favorites, $accessPolicy);

    $result = $handler->__invoke(new ListDirectConversationsQuery('user-1', self::ORG_ID, true, 2, 10));

    self::assertSame(1, $result->page->total);
    self::assertSame('counterpart-member-1', $result->counterpartMemberIds['direct-1']);
    self::assertSame(3, $result->unreadCounts['direct-1']);
    self::assertSame(['direct-1'], $result->favoriteConversationIds);
  }

  #[Test]
  public function testInvokeSkipsBatchLookupsWhenThePageIsEmpty(): void
  {
    $page = new ConversationPage([], 1, 30, 0);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('listDirectConversationsForMember')->willReturn($page);

    /** @var MessagingParticipantRepositoryPort&MockObject $participants */
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('findCounterpartMemberIds');

    /** @var MessagingReadMarkerRepositoryPort&MockObject $readMarkers */
    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('unreadCounts');

    /** @var MessagingConversationFavoriteRepositoryPort&MockObject $favorites */
    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::never())->method('findFavoritedConversationIds');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListDirectConversationsHandler($conversations, $participants, $readMarkers, $favorites, $accessPolicy);

    $result = $handler->__invoke(new ListDirectConversationsQuery('user-1', self::ORG_ID));

    self::assertSame([], $result->counterpartMemberIds);
    self::assertSame([], $result->unreadCounts);
    self::assertSame([], $result->favoriteConversationIds);
  }

  #[Test]
  public function testInvokeThrowsWhenMessagingReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new ListDirectConversationsHandler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new ListDirectConversationsQuery('user-1', self::ORG_ID));
  }
}
