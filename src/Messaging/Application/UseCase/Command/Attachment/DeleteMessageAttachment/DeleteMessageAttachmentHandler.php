<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment;

use Messaging\Application\Port\Outbound\Internal\MessagingAttachmentRepositoryPort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingRevisionPreconditionException};
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Domain\Exception\InvalidValueException;

use function preg_match;

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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Deletes an attachment after verifying uploader ownership or moderation permission.
   *
   * @access public
   *
   * @param MessagingAttachmentRepositoryPort $attachments repository used to find and remove attachment metadata
   * @param MessagingAccessPolicy $accessPolicy policy used to resolve active membership and manager permission
   * @param FileCleanupPort $cleanup schedules file deletion after the owning commit
   * @param RootTransactionManagerPort $transactions commits removal and cleanup together
   *
   * @return void
   */
  public function __construct(
    private MessagingAttachmentRepositoryPort $attachments,
    private MessagingAccessPolicy $accessPolicy,
    private FileCleanupPort $cleanup,
    private RootTransactionManagerPort $transactions,
    private ?HttpIdempotencyExecutionPort $idempotency = null,
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
    return $this->transactions->transactional(function () use ($command): DeleteMessageAttachmentResult {
      $result = $this->delete($command);
      $this->idempotency?->captureResult($result);

      return $result;
    });
  }

  /**
   * Method delete
   *
   * Commits metadata removal and its durable storage cleanup together.
   *
   * @access private
   *
   * @param DeleteMessageAttachmentCommand $command attachment deletion request
   *
   * @return DeleteMessageAttachmentResult deleted attachment identity
   */
  private function delete(DeleteMessageAttachmentCommand $command): DeleteMessageAttachmentResult
  {
    $view = $this->attachments->findViewById($command->attachmentId);
    if (null === $view) {
      throw MessagingAttachmentNotFoundException::withId($command->attachmentId);
    }

    return $this->attachments->withMessageLock($view->messageId, fn () => $this->deleteLocked($command));
  }

  /**
   * Method deleteLocked
   *
   * Rechecks existence and revision while holding the same parent lock as uploads.
   *
   * @access private
   *
   * @param DeleteMessageAttachmentCommand $command attachment mutation request
   *
   * @return DeleteMessageAttachmentResult removed attachment identity
   */
  private function deleteLocked(DeleteMessageAttachmentCommand $command): DeleteMessageAttachmentResult
  {
    $view = $this->attachments->findViewById($command->attachmentId);
    if (null === $view) {
      throw MessagingAttachmentNotFoundException::withId($command->attachmentId);
    }

    try {
      $attachmentId = MessagingAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $attachment = $this->attachments->findById($attachmentId);
    if (null === $attachment) {
      throw MessagingAttachmentNotFoundException::withId($command->attachmentId);
    }

    if (null === $command->ifMatch || '' === $command->ifMatch) {
      throw new MessagingRevisionPreconditionException(missing: true);
    }
    if (1 !== preg_match('/^"revision-(\d+)"$/', $command->ifMatch, $matches)
      || $view->revision !== (int) $matches[1]) {
      throw new MessagingRevisionPreconditionException(missing: false);
    }

    $organizationId = $attachment->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $isUploader = $attachment->uploadedByMemberId() === $actorMemberId;
    $canManage = $this->accessPolicy->hasManagePermission($command->userId, $organizationId);

    if (!$isUploader && !$canManage) {
      throw new MessagingAccessDeniedException('Only the uploading member or a manager can delete this attachment.');
    }

    $this->attachments->delete($attachmentId);
    $this->cleanup->request($attachment->storagePath());

    return new DeleteMessageAttachmentResult(
      attachmentId: (string) $attachmentId,
      messageId: $attachment->messageId(),
      conversationId: $attachment->conversationId(),
    );
  }
  // #endregion
}
