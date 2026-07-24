<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\EditMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingLinkRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Message\EditMessage\{EditMessageCommand, EditMessageHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\{MentionExtractor, UrlExtractor};
use Messaging\Domain\ValueObject\{ConversationId, ConversationVisibility, MessageId, MessagingSubjectType};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Domain\ValueObject\OrganizationNotificationSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Test EditMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EditMessageHandler::class)]
final class EditMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeEditsTheMessageWhenActorIsTheAuthor(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->expects(self::once())->method('save')->willReturn($this->messageView('Updated body'));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $handler = $this->handler($conversations, $messages, $members);

    $result = $handler->__invoke(new EditMessageCommand('user-1', self::MESSAGE_ID, 'Updated body'));

    self::assertSame('Updated body', $result->message->body);
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNotTheAuthor(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('other-member-1');

    $handler = $this->handler($conversations, $messages, $members);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new EditMessageCommand('other-user-1', self::MESSAGE_ID, 'Updated body'));
  }

  #[Test]
  public function testInvokeReExtractsUrlsAndReplacesTheMessageLinkSet(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $view = $this->messageView('New link: https://example.com/updated');
    $messages->method('save')->willReturn($view);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    /** @var MessagingLinkRepositoryPort&MockObject $links */
    $links = $this->createMock(MessagingLinkRepositoryPort::class);
    $links->expects(self::once())
      ->method('replaceForMessage')
      ->with(self::MESSAGE_ID, self::CONVERSATION_ID, ['https://example.com/updated'], $view->updatedAt);

    $handler = $this->handler($conversations, $messages, $members, links: $links);

    $handler->__invoke(new EditMessageCommand('user-1', self::MESSAGE_ID, 'New link: https://example.com/updated'));
  }

  #[Test]
  public function testInvokeLeavesReferencesUntouchedWhenNull(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->method('save')->willReturn($this->messageView('Updated body'));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    // `resolve()` is expected exactly ONCE: the conversation's own
    // subject-permission resolution for the write gate. A second call would
    // mean the handler entered the reference-existence-validation branch
    // despite `references: null` — proving it never does.
    $resolver = $this->createMock(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->expects(self::once())->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    $handler = $this->handler($conversations, $messages, $members, registry: new MessagingSubjectResolverRegistry([$resolver]));

    $result = $handler->__invoke(new EditMessageCommand('user-1', self::MESSAGE_ID, 'Updated body', references: null));

    self::assertSame('Updated body', $result->message->body);
  }

  #[Test]
  public function testInvokeReplacesReferencesWhenGivenAnEmptyList(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());
    $messages->method('save')->willReturn($this->messageView('Updated body'));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $handler = $this->handler($conversations, $messages, $members);

    $result = $handler->__invoke(new EditMessageCommand('user-1', self::MESSAGE_ID, 'Updated body', references: []));

    self::assertSame('Updated body', $result->message->body);
  }

  #[Test]
  public function testInvokeThrowsWhenAReferenceDoesNotExistInTheOrganization(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $registry = new MessagingSubjectResolverRegistry([$this->notFoundEquipmentResolver()]);

    $handler = $this->handler($conversations, $messages, $members, registry: $registry);

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new EditMessageCommand(
      'user-1',
      self::MESSAGE_ID,
      'Updated body',
      references: [['type' => 'equipment', 'id' => 'missing-equipment']],
    ));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingMessageRepositoryPort $messages,
    MessagingMemberDirectoryPort $members,
    ?MessagingLinkRepositoryPort $links = null,
    ?MessagingSubjectResolverRegistry $registry = null,
  ): EditMessageHandler {
    $registry ??= new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $links ??= $this->createStub(MessagingLinkRepositoryPort::class);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());
    $notifications = new MessagingNotificationService($this->createStub(NotificationPort::class), $members, $policy);

    return new EditMessageHandler(
      $messages,
      $conversations,
      $links,
      $registry,
      $accessPolicy,
      $notifications,
      $this->createStub(MessagingRealtimePublisherPort::class),
      new MentionExtractor(),
      new UrlExtractor(),
      $this->createStub(LoggerPort::class),
    );
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function notFoundEquipmentResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type || MessagingSubjectType::EQUIPMENT === $type);
    $resolver->method('resolve')->willReturnCallback(static fn (string $organizationId, string $subjectId): MessagingSubjectResolution => new MessagingSubjectResolution('facility-1' === $subjectId, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function conversation(): Conversation
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Conversation::reconstitute(
      ConversationId::fromString(self::CONVERSATION_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
      ConversationVisibility::SUBJECT,
      null,
      1,
      false,
      $now,
      $now,
    );
  }

  private function message(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Original body',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }

  private function messageView(string $body): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      $body,
      [],
      $now,
      null,
      null,
      $now,
      $now,
    );
  }
}
