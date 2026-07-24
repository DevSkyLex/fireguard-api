<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Link\ListConversationLinks;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Link\MessagingLinkPage;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingLinkRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Link\ListConversationLinks\{ListConversationLinksHandler, ListConversationLinksQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListConversationLinksHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationLinksHandler::class)]
final class ListConversationLinksHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  #[Test]
  public function testInvokeReturnsTheLinkPageWhenSubjectReadPermissionIsGranted(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $page = new MessagingLinkPage([], 1, 30, 0);
    $links = $this->createMock(MessagingLinkRepositoryPort::class);
    $links->expects(self::once())->method('listByConversation')->with(self::CONVERSATION_ID, 1, 30)->willReturn($page);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListConversationLinksHandler($conversations, $links, $registry, $accessPolicy);

    $result = $handler->__invoke(new ListConversationLinksQuery('user-1', self::CONVERSATION_ID));

    self::assertSame(0, $result->page->total);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new ListConversationLinksHandler(
      $conversations,
      $this->createStub(MessagingLinkRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListConversationLinksQuery('user-1', self::CONVERSATION_ID));
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

    $links = $this->createMock(MessagingLinkRepositoryPort::class);
    $links->expects(self::never())->method('listByConversation');

    $handler = new ListConversationLinksHandler($conversations, $links, $registry, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListConversationLinksQuery('user-1', self::CONVERSATION_ID));
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
