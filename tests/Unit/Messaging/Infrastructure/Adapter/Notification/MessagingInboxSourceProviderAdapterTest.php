<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Notification;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Infrastructure\Adapter\Notification\MessagingInboxSourceProviderAdapter;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function mb_strlen;
use function str_repeat;

/**
 * Test MessagingInboxSourceProviderAdapterTest.
 *
 * `MessagingAccessPolicy` is `final`, so it is never mocked directly here
 * (PHPUnit refuses to double a final class) — every test builds a REAL
 * policy instance backed by a stubbed `OrganizationAuthorizationPort`,
 * mirroring how the rest of this module's handler tests already exercise
 * the policy (see `AddChannelParticipantHandlerTest` and siblings).
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingInboxSourceProviderAdapter::class)]
final class MessagingInboxSourceProviderAdapterTest extends TestCase
{
  #[Test]
  public function testSourceKeyReturnsTheMessagingMentionConstant(): void
  {
    $adapter = $this->buildAdapter();

    self::assertSame('messaging.mention', $adapter->sourceKey());
    self::assertSame(MessagingInboxSourceProviderAdapter::SOURCE_KEY, $adapter->sourceKey());
  }

  #[Test]
  public function testFetchReturnsNothingWhenNoOrganizationIsProvided(): void
  {
    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages);

    self::assertSame([], $adapter->fetch('user-1', null, null, 20));
  }

  #[Test]
  public function testFetchReturnsNothingWhenTheUserLacksTheMessagingReadPermission(): void
  {
    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages, accessPolicy: $this->accessPolicy(read: false));

    self::assertSame([], $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchReturnsNothingWhenTheUserIsNotAnActiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages, members: $members);

    self::assertSame([], $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchForwardsTheResolvedMemberCursorAndLimitToTheRepository(): void
  {
    $before = new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())
      ->method('listMentionsForMember')
      ->with('org-1', 'member-1', $before, 15)
      ->willReturn([]);

    $adapter = $this->buildAdapter(messages: $messages, memberId: 'member-1');

    $adapter->fetch('user-1', 'org-1', $before, 15);
  }

  #[Test]
  public function testFetchMapsAnAccessibleMentionInASubjectThreadConversationToAnInboxItem(): void
  {
    $createdAt = new DateTimeImmutable('2026-07-18T09:00:00+00:00');
    $lastReadAt = new DateTimeImmutable('2026-07-18T09:05:00+00:00');
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1', body: 'Please check @{member-1} on this.', createdAt: $createdAt);

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['conversation-1' => 'facility']);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('lastReadAtByConversations')->willReturn(['conversation-1' => $lastReadAt]);

    $accessPolicy = $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => true]);

    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations, readMarkers: $readMarkers, accessPolicy: $accessPolicy);

    $items = $adapter->fetch('user-1', 'org-1', null, 20);

    self::assertCount(1, $items);
    $item = $items[0];
    self::assertSame('messaging.mention', $item->sourceKey);
    self::assertSame('message-1', $item->id);
    self::assertSame('mention', $item->kind);
    self::assertSame('Please check @{member-1} on this.', $item->snippet);
    self::assertEquals($createdAt, $item->occurredAt);
    self::assertTrue($item->isRead);
    self::assertSame('org-1', $item->organizationId);
    self::assertSame('conversation', $item->targetType);
    self::assertSame('conversation-1', $item->targetId);
  }

  #[Test]
  public function testFetchExcludesAMentionWhenTheMemberLacksTheSubjectsReadPermission(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['conversation-1' => 'facility']);

    // The member was mentioned but does not hold `organization.facilities.read`:
    // the mention must never itself grant access.
    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations, accessPolicy: $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => false]));

    self::assertSame([], $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchExcludesAMentionInAChannelTheMemberDoesNotParticipateIn(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'channel-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['channel-1' => 'channel']);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('listChannelIdsForMember')->willReturn(['some-other-channel']);

    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations, participants: $participants);

    self::assertSame([], $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchIncludesAMentionInAChannelTheMemberParticipatesIn(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'channel-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['channel-1' => 'channel']);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('listChannelIdsForMember')->willReturn(['channel-1']);

    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations, participants: $participants);

    self::assertCount(1, $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchIncludesAMentionInAChannelWhenTheMemberManagesMessagingEvenWithoutParticipating(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'channel-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['channel-1' => 'channel']);

    /** @var MessagingParticipantRepositoryPort&MockObject $participants */
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::never())->method('listChannelIdsForMember');

    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations, participants: $participants, accessPolicy: $this->accessPolicy(manage: true));

    self::assertCount(1, $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchExcludesAConversationWithAnUnrecognizedSubjectType(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    // Resolved to nothing (conversation deleted mid-flight): the id is simply
    // absent from the map. Fail closed, never assume access.
    $conversations->method('findSubjectTypesByIds')->willReturn([]);

    $adapter = $this->buildAdapter(messages: $messages, conversations: $conversations);

    self::assertSame([], $adapter->fetch('user-1', 'org-1', null, 20));
  }

  #[Test]
  public function testFetchMarksAMentionUnreadWhenTheLastReadMarkerPredatesTheMessage(): void
  {
    $createdAt = new DateTimeImmutable('2026-07-18T09:00:00+00:00');
    $lastReadAt = new DateTimeImmutable('2026-07-18T08:00:00+00:00');
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1', createdAt: $createdAt);

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['conversation-1' => 'facility']);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('lastReadAtByConversations')->willReturn(['conversation-1' => $lastReadAt]);

    $adapter = $this->buildAdapter(
      messages: $messages,
      conversations: $conversations,
      readMarkers: $readMarkers,
      accessPolicy: $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => true]),
    );

    self::assertFalse($adapter->fetch('user-1', 'org-1', null, 20)[0]->isRead);
  }

  #[Test]
  public function testFetchMarksAMentionUnreadWhenThereIsNoReadMarkerAtAll(): void
  {
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1');

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['conversation-1' => 'facility']);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('lastReadAtByConversations')->willReturn([]);

    $adapter = $this->buildAdapter(
      messages: $messages,
      conversations: $conversations,
      readMarkers: $readMarkers,
      accessPolicy: $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => true]),
    );

    self::assertFalse($adapter->fetch('user-1', 'org-1', null, 20)[0]->isRead);
  }

  #[Test]
  public function testFetchTruncatesALongBodyIntoABoundedSnippet(): void
  {
    $longBody = str_repeat('a', 400);
    $message = $this->message(id: 'message-1', conversationId: 'conversation-1', body: $longBody);

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$message]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn(['conversation-1' => 'facility']);

    $adapter = $this->buildAdapter(
      messages: $messages,
      conversations: $conversations,
      accessPolicy: $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => true]),
    );

    $snippet = $adapter->fetch('user-1', 'org-1', null, 20)[0]->snippet;

    self::assertNotNull($snippet);
    self::assertLessThan(400, mb_strlen($snippet));
    self::assertStringEndsWith('…', $snippet);
  }

  #[Test]
  public function testCountUnreadReturnsZeroWhenNoOrganizationIsProvided(): void
  {
    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages);

    self::assertSame(0, $adapter->countUnread('user-1', null));
  }

  #[Test]
  public function testCountUnreadReturnsZeroWhenTheUserLacksTheMessagingReadPermission(): void
  {
    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages, accessPolicy: $this->accessPolicy(read: false));

    self::assertSame(0, $adapter->countUnread('user-1', 'org-1'));
  }

  #[Test]
  public function testCountUnreadReturnsZeroWhenTheUserIsNotAnActiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listMentionsForMember');

    $adapter = $this->buildAdapter(messages: $messages, members: $members);

    self::assertSame(0, $adapter->countUnread('user-1', 'org-1'));
  }

  #[Test]
  public function testCountUnreadCountsOnlyTheAccessibleUnreadMentions(): void
  {
    $createdAt = new DateTimeImmutable('2026-07-18T09:00:00+00:00');
    $unreadAccessible = $this->message(id: 'message-1', conversationId: 'conversation-1', createdAt: $createdAt);
    $readAccessible = $this->message(id: 'message-2', conversationId: 'conversation-1', createdAt: $createdAt);
    $unreadInaccessible = $this->message(id: 'message-3', conversationId: 'conversation-2', createdAt: $createdAt);

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('listMentionsForMember')->willReturn([$unreadAccessible, $readAccessible, $unreadInaccessible]);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findSubjectTypesByIds')->willReturn([
      'conversation-1' => 'facility',
      // conversation-2 has no resolvable subject type: excluded, fail closed.
    ]);

    $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->method('lastReadAtByConversations')->willReturn([]);

    $adapter = $this->buildAdapter(
      messages: $messages,
      conversations: $conversations,
      readMarkers: $readMarkers,
      accessPolicy: $this->accessPolicy(subjectPermissions: ['organization.facilities.read' => true]),
    );

    // Only message-1 and message-2 are accessible (conversation-1); both are
    // unread (no read marker at all), so the count is 2 — message-3 is
    // excluded by access, not by read status.
    self::assertSame(2, $adapter->countUnread('user-1', 'org-1'));
  }

  #[Test]
  public function testCountUnreadForwardsTheScanLimitToTheRepository(): void
  {
    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())
      ->method('listMentionsForMember')
      ->with('org-1', 'member-1', null, 200)
      ->willReturn([]);

    $adapter = $this->buildAdapter(messages: $messages, memberId: 'member-1');

    $adapter->countUnread('user-1', 'org-1');
  }

  private function message(
    string $id = 'message-1',
    string $conversationId = 'conversation-1',
    string $organizationId = 'org-1',
    string $authorMemberId = 'author-1',
    string $body = 'You were mentioned',
    ?DateTimeImmutable $createdAt = null,
  ): MessageView {
    $createdAt ??= new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    return new MessageView(
      id: $id,
      conversationId: $conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      body: $body,
      mentions: ['member-1'],
      editedAt: null,
      deletedAt: null,
      deletedByMemberId: null,
      createdAt: $createdAt,
      updatedAt: $createdAt,
    );
  }

  /**
   * Method accessPolicy.
   *
   * Builds a REAL `MessagingAccessPolicy` (it is `final`, so it cannot be
   * doubled) backed by a stubbed `OrganizationAuthorizationPort` returning
   * deterministic answers per permission name.
   *
   * @param bool $read the `organization.messaging.read` answer
   * @param bool $manage the `organization.messaging.manage` answer
   * @param array<string, bool> $subjectPermissions per-subject-permission answers (e.g. `organization.facilities.read`); any permission not listed here answers `false`
   */
  private function accessPolicy(bool $read = true, bool $manage = false, array $subjectPermissions = []): MessagingAccessPolicy
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(
      static fn (string $userId, string $organizationId, string $permission): bool => match ($permission) {
        'organization.messaging.read' => $read,
        'organization.messaging.manage' => $manage,
        default => $subjectPermissions[$permission] ?? false,
      },
    );

    return new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));
  }

  private function buildAdapter(
    ?MessagingMessageRepositoryPort $messages = null,
    ?MessagingConversationRepositoryPort $conversations = null,
    ?MessagingReadMarkerRepositoryPort $readMarkers = null,
    ?MessagingParticipantRepositoryPort $participants = null,
    ?MessagingMemberDirectoryPort $members = null,
    ?MessagingAccessPolicy $accessPolicy = null,
    string $memberId = 'member-1',
  ): MessagingInboxSourceProviderAdapter {
    if (null === $messages) {
      $messages = $this->createStub(MessagingMessageRepositoryPort::class);
      $messages->method('listMentionsForMember')->willReturn([]);
    }

    if (null === $conversations) {
      $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
      $conversations->method('findSubjectTypesByIds')->willReturn([]);
    }

    if (null === $readMarkers) {
      $readMarkers = $this->createStub(MessagingReadMarkerRepositoryPort::class);
      $readMarkers->method('lastReadAtByConversations')->willReturn([]);
    }

    $participants ??= $this->createStub(MessagingParticipantRepositoryPort::class);

    if (null === $members) {
      $members = $this->createStub(MessagingMemberDirectoryPort::class);
      $members->method('resolveActiveMemberId')->willReturn($memberId);
    }

    $accessPolicy ??= $this->accessPolicy();

    return new MessagingInboxSourceProviderAdapter($messages, $conversations, $readMarkers, $participants, $members, $accessPolicy);
  }
}
