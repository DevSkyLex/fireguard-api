<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment;

use DateTimeImmutable;
use Messaging\Application\Contract\Attachment\MessagingAttachmentView;
use Messaging\Application\Port\Outbound\Internal\MessagingAttachmentRepositoryPort;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\{DeleteMessageAttachmentCommand, DeleteMessageAttachmentHandler, DeleteMessageAttachmentResult};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException};
use Messaging\Domain\Model\Attachment\{MessagingAttachment, MessagingAttachmentFile};
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};

/**
 * Test DeleteMessageAttachmentHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteMessageAttachmentHandler::class)]
final class DeleteMessageAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string UPLOADER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string MANAGER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testInvokeSelfDeleteByUploaderRemovesTheAttachment(): void
  {
    /** @var MessagingAttachmentRepositoryPort&MockObject $attachments */
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());
    $attachments->method('findViewById')->willReturn($this->view());
    $attachments->method('withMessageLock')->willReturnCallback(static fn (string $id, callable $operation) => $operation());
    $attachments->expects(self::once())->method('delete');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::UPLOADER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var FileCleanupPort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileCleanupPort::class);
    $fileStorage->expects(self::once())->method('request');

    $handler = new DeleteMessageAttachmentHandler(
      $attachments,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $fileStorage,
      $this->transactions(),
    );

    $result = $handler->__invoke(new DeleteMessageAttachmentCommand('uploader-user-1', self::ATTACHMENT_ID, '"revision-1"'));

    self::assertInstanceOf(DeleteMessageAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeManagerDeletingAnotherMembersAttachmentSucceeds(): void
  {
    /** @var MessagingAttachmentRepositoryPort&MockObject $attachments */
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());
    $attachments->method('findViewById')->willReturn($this->view());
    $attachments->method('withMessageLock')->willReturnCallback(static fn (string $id, callable $operation) => $operation());
    $attachments->expects(self::once())->method('delete');

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MANAGER_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var FileCleanupPort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileCleanupPort::class);
    $fileStorage->expects(self::once())->method('request');

    $handler = new DeleteMessageAttachmentHandler(
      $attachments,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $fileStorage,
      $this->transactions(),
    );

    $handler->__invoke(new DeleteMessageAttachmentCommand('manager-user-1', self::ATTACHMENT_ID, '"revision-1"'));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNeitherUploaderNorManager(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($this->attachment());
    $attachments->method('findViewById')->willReturn($this->view());
    $attachments->method('withMessageLock')->willReturnCallback(static fn (string $id, callable $operation) => $operation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('other-member-1');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $fileStorage = $this->createMock(FileCleanupPort::class);
    $fileStorage->expects(self::never())->method('request');

    $handler = new DeleteMessageAttachmentHandler(
      $attachments,
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $fileStorage,
      $this->transactions(),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new DeleteMessageAttachmentCommand('other-user-1', self::ATTACHMENT_ID, '"revision-1"'));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentNotFound(): void
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn(null);

    $fileStorage = $this->createMock(FileCleanupPort::class);
    $fileStorage->expects(self::never())->method('request');

    $handler = new DeleteMessageAttachmentHandler(
      $attachments,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $fileStorage,
      $this->transactions(),
    );

    $this->expectException(MessagingAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteMessageAttachmentCommand('user-1', self::ATTACHMENT_ID, '"revision-1"'));
  }

  #[Test]
  public function testInvokeRejectsAMalformedAttachmentIdentifier(): void
  {
    $handler = new DeleteMessageAttachmentHandler(
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      new MessagingAccessPolicy(
        $this->createStub(OrganizationAuthorizationPort::class),
        $this->createStub(MessagingMemberDirectoryPort::class),
        $this->createStub(MessagingParticipantRepositoryPort::class),
      ),
      $this->createStub(FileCleanupPort::class),
      $this->transactions(),
    );

    $this->expectException(MessagingAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteMessageAttachmentCommand('user-1', 'not-a-uuid'));
  }

  private function transactions(): RootTransactionManagerPort
  {
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());

    return $transactions;
  }

  private function view(): MessagingAttachmentView
  {
    return new MessagingAttachmentView(self::ATTACHMENT_ID, 'message-1', 'conversation-1', self::ORG_ID, self::UPLOADER_MEMBER_ID, 'floor-plan.pdf', 'application/pdf', 12345, null, new DateTimeImmutable());
  }

  private function attachment(): MessagingAttachment
  {
    return MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString(self::ATTACHMENT_ID),
      messageId: 'message-1',
      conversationId: 'conversation-1',
      organizationId: self::ORG_ID,
      uploadedByMemberId: self::UPLOADER_MEMBER_ID,
      file: new MessagingAttachmentFile(
        fileName: 'floor-plan.pdf',
        storagePath: 'messaging/conversation-1/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
        mimeType: 'application/pdf',
        size: 12345,
      ),
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
