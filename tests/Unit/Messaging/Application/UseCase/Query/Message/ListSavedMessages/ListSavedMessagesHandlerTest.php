<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Message\ListSavedMessages;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Message\ListSavedMessages\{ListSavedMessagesHandler, ListSavedMessagesQuery};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListSavedMessagesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListSavedMessagesHandler::class)]
final class ListSavedMessagesHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string MEMBER_ID = 'member-1';

  #[Test]
  public function testInvokeListsTheMembersSavedMessages(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new MessageView('message-1', 'conversation-1', self::ORG_ID, 'author-1', 'Hello team', [], null, null, null, $now, $now);
    $page = new MessagePage([$view], 1, 30, 1);

    /** @var MessagingMessageRepositoryPort&MockObject $messages */
    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())
      ->method('listSavedByMember')
      ->with(self::ORG_ID, self::MEMBER_ID, 1, 30)
      ->willReturn($page);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListSavedMessagesHandler($messages, $accessPolicy);

    $result = $handler->__invoke(new ListSavedMessagesQuery('user-1', self::ORG_ID));

    self::assertSame(1, $result->page->total);
    self::assertSame(self::MEMBER_ID, $result->currentMemberId);
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNotAnActiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new ListSavedMessagesHandler(
      $this->createStub(MessagingMessageRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListSavedMessagesQuery('user-1', self::ORG_ID));
  }
}
