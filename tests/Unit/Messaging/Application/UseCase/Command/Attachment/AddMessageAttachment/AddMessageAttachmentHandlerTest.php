<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentHandler, AddMessageAttachmentResult};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\{MessageId, MessagingAttachmentId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test AddMessageAttachmentHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddMessageAttachmentHandler::class)]
final class AddMessageAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeStoresTheAttachmentWhenAuthorized(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    /** @var MessagingAttachmentRepositoryPort&MockObject $attachments */
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::never())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new MessagingAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      $registry,
      $accessPolicy,
      $attachments,
      $fileStorage,
      $uuidFactory,
    );

    $result = $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
      label: 'Floor plan',
    ));

    self::assertInstanceOf(AddMessageAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame(self::MESSAGE_ID, $result->messageId);
    self::assertSame(self::CONVERSATION_ID, $result->conversationId);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $attachments,
      $fileStorage,
      $this->createStub(UuidFactory::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsDeleted(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->deletedMessage());

    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $attachments,
      $fileStorage,
      $this->createStub(UuidFactory::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSubjectPermissionIsMissing(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      new MessagingAccessDeniedException('Missing permission.'),
    );

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      $registry,
      $accessPolicy,
      $attachments,
      $fileStorage,
      $this->createStub(UuidFactory::class),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeDeletesTheFileWhenTheDatabaseSaveFails(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    /** @var MessagingAttachmentRepositoryPort&MockObject $attachments */
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->expects(self::once())->method('save')->willThrowException(new RuntimeException('Database error.'));

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new MessagingAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      $registry,
      $accessPolicy,
      $attachments,
      $fileStorage,
      $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database error.');

    $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy(
        $this->createStub(OrganizationAuthorizationPort::class),
        $this->createStub(MessagingMemberDirectoryPort::class),
        $this->createStub(MessagingParticipantRepositoryPort::class),
      ),
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      $this->createStub(FileStoragePort::class),
      $this->createStub(UuidFactory::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke($this->command());
  }

  #[Test]
  public function testInvokeGatesAChannelUploadOnChannelParticipation(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation(visibility: 'participants'));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    /** @var MessagingParticipantRepositoryPort&MockObject $participants */
    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())
      ->method('isParticipant')
      ->with(self::CONVERSATION_ID, self::AUTHOR_MEMBER_ID)
      ->willReturn(true);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new MessagingAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      $this->createStub(FileStoragePort::class),
      $uuidFactory,
    );

    $result = $handler->__invoke($this->command());

    self::assertInstanceOf(AddMessageAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeHonoursAClientSuppliedAttachmentId(): void
  {
    $clientAttachmentId = '550e8400-e29b-41d4-a716-446655440077';

    $handler = $this->authorizedHandler();

    $result = $handler->__invoke($this->command(attachmentId: $clientAttachmentId));

    self::assertSame($clientAttachmentId, $result->attachmentId);
  }

  #[Test]
  public function testInvokeRefusesAMalformedClientSuppliedAttachmentId(): void
  {
    $handler = $this->authorizedHandler();

    $this->expectException(InvalidValueException::class);

    $handler->__invoke($this->command(attachmentId: 'not-a-uuid'));
  }

  #[Test]
  public function testInvokeRejectsAnUploadWhenTheMessageIsAtTheAttachmentCap(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    $accessPolicy = new MessagingAccessPolicy(
      $this->createStub(OrganizationAuthorizationPort::class),
      $members,
      $this->createStub(MessagingParticipantRepositoryPort::class),
    );

    // The cap is per MESSAGE, not per conversation.
    /** @var MessagingAttachmentRepositoryPort&MockObject $attachments */
    $attachments = $this->createMock(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn(null);
    $attachments->method('countByMessageId')
      ->with(self::MESSAGE_ID)
      ->willReturn(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT);
    $attachments->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new MessagingAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      $accessPolicy,
      $attachments,
      $fileStorage,
      $uuidFactory,
    );

    $this->expectException(InvalidAttachmentException::class);

    $handler->__invoke(new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  private function authorizedHandler(): AddMessageAttachmentHandler
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversation());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::AUTHOR_MEMBER_ID);

    return new AddMessageAttachmentHandler(
      $messages,
      $conversations,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingAttachmentRepositoryPort::class),
      $this->createStub(FileStoragePort::class),
      $this->createStub(UuidFactory::class),
    );
  }

  private function command(?string $attachmentId = null): AddMessageAttachmentCommand
  {
    return new AddMessageAttachmentCommand(
      userId: 'user-1',
      messageId: self::MESSAGE_ID,
      fileName: 'floor-plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
      attachmentId: $attachmentId,
    );
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function message(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }

  private function deletedMessage(): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      $now,
      self::AUTHOR_MEMBER_ID,
      $now,
      $now,
    );
  }

  private function conversation(string $visibility = 'subject'): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', $visibility, null, 1, false, $now, $now);
  }
}
