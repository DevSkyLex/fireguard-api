<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use InvalidArgumentException;
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingAttachmentId, MessagingSubjectType};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\StoragePathScheme;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

/**
 * UseCase AddMessageAttachmentHandler.
 *
 * Uploads a file onto an existing message: resolves the owning conversation
 * to gate by the subject's own write context (a v1 subject thread) or
 * participant membership (a v2 channel) — mirroring `PostMessageHandler` —
 * then writes the file BEFORE persisting the attachment record, rolling the
 * write back on a database failure (mirrors `AddInspectionAttachmentHandler`).
 * Refuses to attach a file to an already-tombstoned message.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingAttachmentRepositoryPort $attachments,
    private FileStoragePort $fileStorage,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddMessageAttachmentCommand $command): AddMessageAttachmentResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    if ($message->isDeleted()) {
      throw new MessagingValidationException('Cannot attach a file to a deleted message.');
    }

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();

    $conversation = $this->conversations->findById($conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($conversationId);
    }

    $uploaderMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $conversationId, $uploaderMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    try {
      /** @var MessagingAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? $this->uuidFactory->create(MessagingAttachmentId::class)
        : MessagingAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $storagePath = StoragePathScheme::build(
      module: 'messaging',
      parentId: $conversationId,
      attachmentId: (string) $attachmentId,
      fileName: $command->fileName,
    );

    $attachment = MessagingAttachment::create(
      id: $attachmentId,
      messageId: $command->messageId,
      conversationId: $conversationId,
      organizationId: $organizationId,
      uploadedByMemberId: $uploaderMemberId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      label: $command->label,
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->attachments->save($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    return new AddMessageAttachmentResult(
      attachmentId: (string) $attachment->id(),
      messageId: $attachment->messageId(),
      conversationId: $attachment->conversationId(),
      organizationId: $attachment->organizationId(),
      uploadedByMemberId: $attachment->uploadedByMemberId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
    );
  }
  // #endregion
}
