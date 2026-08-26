<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment;

use Messaging\Application\Port\Outbound\MessagingAttachmentRepositoryPort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException};
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteMessageAttachmentHandler.
 *
 * Deletes an attachment: the uploading member may always delete their own
 * upload; deleting someone else's attachment additionally requires
 * `organization.messaging.manage` — mirrors `DeleteMessageHandler`'s
 * self-delete-vs-moderation split. The owning organization is derived from
 * the loaded attachment, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private MessagingAttachmentRepositoryPort $attachments,
    private MessagingAccessPolicy $accessPolicy,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(DeleteMessageAttachmentCommand $command): DeleteMessageAttachmentResult
  {
    try {
      $attachmentId = MessagingAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $attachment = $this->attachments->findById($attachmentId);
    if (null === $attachment) {
      throw MessagingAttachmentNotFoundException::withId($command->attachmentId);
    }

    $organizationId = $attachment->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $isUploader = $attachment->uploadedByMemberId() === $actorMemberId;
    $canManage = $this->accessPolicy->hasManagePermission($command->userId, $organizationId);

    if (!$isUploader && !$canManage) {
      throw new MessagingAccessDeniedException('Only the uploading member or a manager can delete this attachment.');
    }

    $this->attachments->delete($attachmentId);
    $this->fileStorage->delete($attachment->storagePath());

    return new DeleteMessageAttachmentResult(
      attachmentId: (string) $attachmentId,
      messageId: $attachment->messageId(),
      conversationId: $attachment->conversationId(),
    );
  }
  // #endregion
}
