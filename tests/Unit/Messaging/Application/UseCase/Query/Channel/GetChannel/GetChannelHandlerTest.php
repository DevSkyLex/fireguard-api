<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Channel\GetChannel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Channel\GetChannel\{GetChannelHandler, GetChannelQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetChannelHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetChannelHandler::class)]
final class GetChannelHandlerTest extends TestCase
{
  private const string USER_ID = 'user-1';

  private const string ORG_ID = 'org-1';

  private const string CHANNEL_ID = 'channel-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeReturnsTheChannelWithUnreadCountAndFavoriteFlagForAParticipant(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn([self::CHANNEL_ID => 7]);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn([self::CHANNEL_ID]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $handler = new GetChannelHandler($conversations, $readMarkers, $favorites, $this->accessPolicy($members, $participants));

    $result = $handler->__invoke(new GetChannelQuery(self::USER_ID, self::CHANNEL_ID));

    self::assertSame(self::CHANNEL_ID, $result->channel->id);
    self::assertSame(7, $result->unreadCount);
    self::assertTrue($result->isFavorite);
  }

  #[Test]
  public function testInvokeLetsAManagerReadAChannelWithoutBeingAParticipant(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('unreadCounts')->willReturn([]);

    $favorites = $this->createStub(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->method('findFavoritedConversationIds')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);
    $handler = new GetChannelHandler($conversations, $readMarkers, $favorites, $accessPolicy);

    $result = $handler->__invoke(new GetChannelQuery(self::USER_ID, self::CHANNEL_ID));

    self::assertSame(self::CHANNEL_ID, $result->channel->id);
    self::assertSame(0, $result->unreadCount);
    self::assertFalse($result->isFavorite);
  }

  #[Test]
  public function testInvokeThrowsWhenTheChannelIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn(null);

    $handler = new GetChannelHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      $this->accessPolicy($this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new GetChannelQuery(self::USER_ID, self::CHANNEL_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserIsNotAParticipant(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $handler = new GetChannelHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      $this->accessPolicy($members, $participants),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new GetChannelQuery(self::USER_ID, self::CHANNEL_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheUserIsNotAnActiveMember(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findChannelById')->willReturn($this->channel());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new GetChannelHandler(
      $conversations,
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      $this->accessPolicy($members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new GetChannelQuery(self::USER_ID, self::CHANNEL_ID));
  }

  private function accessPolicy(MessagingMemberDirectoryPort $members, MessagingParticipantRepositoryPort $participants): MessagingAccessPolicy
  {
    return new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants);
  }

  private function channel(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHANNEL_ID, self::ORG_ID, 'general', null, self::MEMBER_ID, 3, false, $now, 10, $now, $now);
  }
}
