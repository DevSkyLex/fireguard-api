<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment;

use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingAttachmentNotFoundException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingAttachmentId, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DownloadMessageAttachmentHandler.
 *
 * Serves an attachment's stored bytes to a caller who may READ the owning
 * conversation — gated by the EXACT same access rule as
 * `ListConversationAttachmentsHandler`/`ListMessagesHandler` (a single read
 * access path for a conversation's content), so a member who can open the
 * Files tab can download any file listed in it, and no one else can. The
 * owning conversation and organization are derived from the loaded
 * attachment, never supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadMessageAttachmentHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private MessagingAttachmentRepositoryPort $attachments,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSubjectResolverRegistry $resolvers,
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
  public function __invoke(DownloadMessageAttachmentQuery $query): DownloadMessageAttachmentResult
  {
    try {
      $attachmentId = MessagingAttachmentId::fromString($query->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $attachment = $this->attachments->findById($attachmentId);
    if (null === $attachment) {
      throw MessagingAttachmentNotFoundException::withId($query->attachmentId);
    }

    $conversation = $this->conversations->findById($attachment->conversationId());
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($attachment->conversationId());
    }

    // Membership is resolved BEFORE the visibility branch, not inside it.
    // `resolveActiveMemberId()` answers 404 for a caller outside the
    // conversation's organization, whereas `assertCanReadThread()` answers 403 —
    // which would confirm to an outsider that the conversation exists. Nine
    // sibling handlers already hoist it; these four did not.
    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanReadChannel($query->userId, $conversation->organizationId, $conversation->id, $memberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $conversation->organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($query->userId, $conversation->organizationId, $requiredSubjectPermission);
    }

    $contents = $this->fileStorage->read($attachment->storagePath());

    return new DownloadMessageAttachmentResult(
      contents: $contents,
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
    );
  }
  // #endregion
}
