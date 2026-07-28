<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment;

use DateTimeImmutable;
use InvalidArgumentException;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment\{DownloadMessageAttachmentHandler, DownloadMessageAttachmentQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingNotFoundException};
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\{MessagingAttachmentId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

/**
 * Test DownloadMessageAttachmentHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DownloadMessageAttachmentHandler::class)]
final class DownloadMessageAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string ATTACHMENT_ID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function testInvokeReturnsTheStoredBytesWhenSubjectReadPermissionIsGranted(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('PDF-BYTES');

    $handler = new DownloadMessageAttachmentHandler(
      $attachments,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      $this->accessPolicy($this->createStub(OrganizationAuthorizationPort::class)),
      $fileStorage,
    );

    $result = $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', self::ATTACHMENT_ID));

    self::assertSame('PDF-BYTES', $result->contents);
    self::assertSame('report.pdf', $result->fileName);
    self::assertSame('application/pdf', $result->mimeType);
    self::assertSame(9, $result->size);
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsNotFound(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn(null);

    $handler = new DownloadMessageAttachmentHandler(
      $attachments,
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      $this->accessPolicy($this->createStub(OrganizationAuthorizationPort::class)),
      $this->createStub(FileStoragePort::class),
    );

    $this->expectException(MessagingAttachmentNotFoundException::class);

    $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', self::ATTACHMENT_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new DownloadMessageAttachmentHandler(
      $attachments,
      $conversations,
      new MessagingSubjectResolverRegistry([]),
      $this->accessPolicy($this->createStub(OrganizationAuthorizationPort::class)),
      $this->createStub(FileStoragePort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', self::ATTACHMENT_ID));
  }

  #[Test]
  public function testInvokeNeverReadsTheFileWhenSubjectReadPermissionIsMissing(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      new MessagingAccessDeniedException('Missing permission.'),
    );

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new DownloadMessageAttachmentHandler(
      $attachments,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      $this->accessPolicy($authorization),
      $fileStorage,
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', self::ATTACHMENT_ID));
  }

  #[Test]
  public function testInvokeThrowsOnAMalformedAttachmentId(): void
  {
    $handler = new DownloadMessageAttachmentHandler(
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      $this->accessPolicy($this->createStub(OrganizationAuthorizationPort::class)),
      $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', 'not-a-uuid'));
  }

  #[Test]
  public function testInvokeGatesAChannelDownloadOnChannelParticipation(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view(visibility: 'participants'));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    /** @var MessagingParticipantRepositoryPort&MockObject $participants */
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())
      ->method('isParticipant')
      ->with(self::CONVERSATION_ID, 'member-1')
      ->willReturn(true);

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('PDF-BYTES');

    $handler = new DownloadMessageAttachmentHandler(
      $attachments,
      $conversations,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
      $fileStorage,
    );

    $result = $handler->__invoke(new DownloadMessageAttachmentQuery('user-1', self::ATTACHMENT_ID));

    self::assertSame('PDF-BYTES', $result->contents);
  }

  private function accessPolicy(OrganizationAuthorizationPort $authorization): MessagingAccessPolicy
  {
    return new MessagingAccessPolicy(
      $authorization,
      $this->createStub(MessagingMemberDirectoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
    );
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function attachment(): MessagingAttachment
  {
    return MessagingAttachment::create(
      id: MessagingAttachmentId::fromString(self::ATTACHMENT_ID),
      messageId: 'message-1',
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORG_ID,
      uploadedByMemberId: 'member-1',
      fileName: 'report.pdf',
      storagePath: 'messaging/' . self::CONVERSATION_ID . '/' . self::ATTACHMENT_ID . '-report.pdf',
      mimeType: 'application/pdf',
      size: 9,
      label: null,
    );
  }

  private function view(string $visibility = 'subject'): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', $visibility, null, 0, false, $now, $now);
  }
}
