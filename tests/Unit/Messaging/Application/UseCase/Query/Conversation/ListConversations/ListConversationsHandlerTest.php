<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Conversation\ListConversations;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Conversation\ListConversations\{ListConversationsHandler, ListConversationsQuery};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListConversationsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationsHandler::class)]
final class ListConversationsHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeEnrichesTheListedConversationsWithUnreadCounts(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new ConversationView('conversation-1', self::ORG_ID, 'facility', 'facility-1', 'subject', $now, 2, false, $now, $now);
    $page = new ConversationPage([$view], 1, 30, 1);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('list')->willReturn($page);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn(['conversation-1' => 2]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn(['conversation-1']);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListConversationsHandler($conversations, $readMarkers, $favorites, $accessPolicy);

    $result = $handler->__invoke(new ListConversationsQuery('user-1', self::ORG_ID));

    self::assertSame(1, $result->page->total);
    self::assertSame(2, $result->unreadCounts['conversation-1']);
    self::assertSame(['conversation-1'], $result->favoriteConversationIds);
  }

  #[Test]
  public function testInvokeThrowsWhenMessagingReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new ListConversationsHandler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new ListConversationsQuery('user-1', self::ORG_ID));
  }
}
