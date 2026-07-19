<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments;

use DateTimeImmutable;
use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments\{ListConversationAttachmentsHandler, ListConversationAttachmentsQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListConversationAttachmentsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationAttachmentsHandler::class)]
final class ListConversationAttachmentsHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  #[Test]
  public function testInvokeReturnsThePageWhenSubjectReadPermissionIsGranted(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $page = new MessagingAttachmentPage([], 1, 30, 0);
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('listByConversationId')->willReturn($page);

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new ListConversationAttachmentsHandler($conversations, $attachments, $registry, $accessPolicy);

    $result = $handler->__invoke(new ListConversationAttachmentsQuery('user-1', self::CONVERSATION_ID));

    self::assertSame(0, $result->page->total);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new ListConversationAttachmentsHandler(
      $conversations,
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new ListConversationAttachmentsQuery('user-1', self::CONVERSATION_ID));
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

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::never())->method('listByConversationId');

    $handler = new ListConversationAttachmentsHandler($conversations, $attachments, $registry, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new ListConversationAttachmentsQuery('user-1', self::CONVERSATION_ID));
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
