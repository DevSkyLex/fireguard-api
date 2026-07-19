<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Message\ListReplies;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Message\ListReplies\{ListRepliesHandler, ListRepliesQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListRepliesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListRepliesHandler::class)]
final class ListRepliesHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string PARENT_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testInvokeReturnsTheReplyPageWhenSubjectReadPermissionIsGranted(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($this->parentView());
    $page = new MessagePage([], 1, 30, 0);
    $messages->expects(self::once())->method('listRepliesByParent')->with(self::PARENT_MESSAGE_ID, 1, 30)->willReturn($page);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListRepliesHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new ListRepliesQuery('user-1', self::PARENT_MESSAGE_ID));

    self::assertSame(0, $result->page->total);
    self::assertSame('member-1', $result->currentMemberId);
  }

  #[Test]
  public function testInvokeThrowsWhenTheParentMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturn(null);

    $handler = new ListRepliesHandler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $messages,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListRepliesQuery('user-1', self::PARENT_MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($this->parentView());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new ListRepliesHandler(
      $conversations,
      $messages,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListRepliesQuery('user-1', self::PARENT_MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenSubjectReadPermissionIsMissing(): void
  {
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($this->parentView());
    $messages->expects(self::never())->method('listRepliesByParent');

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      new MessagingAccessDeniedException('Missing permission.'),
    );

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListRepliesHandler($conversations, $messages, $registry, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListRepliesQuery('user-1', self::PARENT_MESSAGE_ID));
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 0, false, $now, $now);
  }

  private function parentView(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::PARENT_MESSAGE_ID,
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      'The root message',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }
}
