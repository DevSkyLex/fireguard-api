<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Message\ListPinnedMessages;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Message\MessagePage;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Message\ListPinnedMessages\{ListPinnedMessagesHandler, ListPinnedMessagesQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListPinnedMessagesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPinnedMessagesHandler::class)]
final class ListPinnedMessagesHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  #[Test]
  public function testInvokeReturnsThePinnedPageWhenSubjectReadPermissionIsGranted(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $page = new MessagePage([], 1, 30, 0);
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())->method('listPinnedByConversation')->with(self::CONVERSATION_ID, 1, 30)->willReturn($page);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListPinnedMessagesHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new ListPinnedMessagesQuery('user-1', self::CONVERSATION_ID));

    self::assertSame(0, $result->page->total);
    self::assertSame('member-1', $result->currentMemberId);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new ListPinnedMessagesHandler(
      $conversations,
      $this->createStub(MessagingMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListPinnedMessagesQuery('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenSubjectReadPermissionIsMissing(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      new MessagingAccessDeniedException('Missing permission.'),
    );

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('listPinnedByConversation');

    $handler = new ListPinnedMessagesHandler($conversations, $messages, $registry, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListPinnedMessagesQuery('user-1', self::CONVERSATION_ID));
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

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 0, false, $now, $now);
  }
}
