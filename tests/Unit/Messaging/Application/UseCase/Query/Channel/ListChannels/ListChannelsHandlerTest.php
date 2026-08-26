<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Channel\ListChannels;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelPage, ChannelView};
use Messaging\Application\Port\Outbound\{
  MessagingConversationFavoriteRepositoryPort,
  MessagingConversationRepositoryPort,
  MessagingMemberDirectoryPort,
  MessagingParticipantRepositoryPort,
  MessagingReadMarkerRepositoryPort
};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Channel\ListChannels\{ListChannelsHandler, ListChannelsQuery};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListChannelsHandlerTest.
 *
 * Unread badges are what draw people back into a channel, so the handler
 * must decorate the page with real read-marker counts rather than the
 * fabricated zero the provider used to pass through. It also has to skip
 * the marker and favourite look-ups entirely on an empty page, since
 * querying with an empty id list is both pointless and expensive.
 *
 * @category Handler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelsHandler::class)]
final class ListChannelsHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655482001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655482002';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655482003';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655482004';
  // #endregion

  // #region Methods
  #[Test]
  public function testHandlerDecoratesThePageWithUnreadCountsAndFavourites(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('listChannelsForMember')
      ->willReturn(new ChannelPage([$this->view()], 1, 30, 1));

    /** @var MessagingReadMarkerRepositoryPort&MockObject $readMarkers */
    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())
      ->method('unreadCounts')
      ->with(self::ORGANIZATION_ID, self::MEMBER_ID, [self::CHANNEL_ID])
      ->willReturn([self::CHANNEL_ID => 5]);

    /** @var MessagingConversationFavoriteRepositoryPort&MockObject $favorites */
    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::once())
      ->method('findFavoritedConversationIds')
      ->with(self::MEMBER_ID, [self::CHANNEL_ID])
      ->willReturn([self::CHANNEL_ID]);

    $result = new ListChannelsHandler(
      conversations: $conversations,
      readMarkers: $readMarkers,
      favorites: $favorites,
      accessPolicy: $this->accessPolicy(),
    )(new ListChannelsQuery(userId: self::USER_ID, organizationId: self::ORGANIZATION_ID));

    self::assertCount(1, $result->page->items);
    self::assertSame([self::CHANNEL_ID => 5], $result->unreadCounts);
    self::assertSame([self::CHANNEL_ID], $result->favoriteChannelIds);
  }

  #[Test]
  public function testHandlerForwardsTheFiltersToTheRepository(): void
  {
    /** @var MessagingConversationRepositoryPort&MockObject $conversations */
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::once())
      ->method('listChannelsForMember')
      ->with(self::ORGANIZATION_ID, self::MEMBER_ID, true, 3, 50)
      ->willReturn(new ChannelPage([], 3, 50, 0));

    new ListChannelsHandler(
      conversations: $conversations,
      readMarkers: $this->createStub(MessagingReadMarkerRepositoryPort::class),
      favorites: $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      accessPolicy: $this->accessPolicy(),
    )(new ListChannelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      isArchived: true,
      page: 3,
      itemsPerPage: 50,
    ));
  }

  #[Test]
  public function testHandlerSkipsTheLookUpsOnAnEmptyPage(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('listChannelsForMember')->willReturn(new ChannelPage([], 1, 30, 0));

    /** @var MessagingReadMarkerRepositoryPort&MockObject $readMarkers */
    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('unreadCounts');

    /** @var MessagingConversationFavoriteRepositoryPort&MockObject $favorites */
    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::never())->method('findFavoritedConversationIds');

    $result = new ListChannelsHandler(
      conversations: $conversations,
      readMarkers: $readMarkers,
      favorites: $favorites,
      accessPolicy: $this->accessPolicy(),
    )(new ListChannelsQuery(userId: self::USER_ID, organizationId: self::ORGANIZATION_ID));

    self::assertSame([], $result->unreadCounts);
    self::assertSame([], $result->favoriteChannelIds);
  }

  #[Test]
  public function testHandlerRefusesACallerWithoutTheReadPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing messaging read permission.'));

    $this->expectException(OrganizationAccessDeniedException::class);

    new ListChannelsHandler(
      conversations: $this->createStub(MessagingConversationRepositoryPort::class),
      readMarkers: $this->createStub(MessagingReadMarkerRepositoryPort::class),
      favorites: $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      accessPolicy: $this->accessPolicy(authorization: $authorization),
    )(new ListChannelsQuery(userId: self::USER_ID, organizationId: self::ORGANIZATION_ID));
  }

  #[Test]
  public function testHandlerRefusesACallerWhoIsNotAnActiveMember(): void
  {
    $this->expectException(MessagingNotFoundException::class);

    new ListChannelsHandler(
      conversations: $this->createStub(MessagingConversationRepositoryPort::class),
      readMarkers: $this->createStub(MessagingReadMarkerRepositoryPort::class),
      favorites: $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      accessPolicy: $this->accessPolicy(memberId: null),
    )(new ListChannelsQuery(userId: self::USER_ID, organizationId: self::ORGANIZATION_ID));
  }

  private function accessPolicy(
    ?OrganizationAuthorizationPort $authorization = null,
    ?string $memberId = self::MEMBER_ID,
  ): MessagingAccessPolicy {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn($memberId);

    return new MessagingAccessPolicy(
      authorization: $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      members: $members,
      participants: $this->createStub(MessagingParticipantRepositoryPort::class),
    );
  }

  private function view(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(
      self::CHANNEL_ID,
      self::ORGANIZATION_ID,
      'General',
      null,
      self::MEMBER_ID,
      2,
      false,
      null,
      7,
      $now,
      $now,
      null,
    );
  }
  // #endregion
}
