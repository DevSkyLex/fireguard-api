<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;

/**
 * Port MessagingAttachmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingAttachmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a messaging attachment.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachment $attachment the attachment
   */
  public function save(MessagingAttachment $attachment): void;

  /**
   * Method findById.
   *
   * Finds an attachment by identifier.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentId $id the attachment identifier
   *
   * @return ?MessagingAttachment the attachment when found
   */
  public function findById(MessagingAttachmentId $id): ?MessagingAttachment;

  /**
   * Method findByMessageIds.
   *
   * Batch-loads every attachment posted with any of the given messages, in a
   * single query — used to populate `MessageOutput::$attachments` for a
   * whole message page without one query per message (N+1).
   *
   * @since 1.0.0
   *
   * @param list<string> $messageIds the owning message identifiers
   *
   * @return list<MessagingAttachment> the attachment list
   */
  public function findByMessageIds(array $messageIds): array;

  /**
   * Method countByMessageId.
   *
   * Counts the attachments a message already carries, without hydrating
   * them — the input of the
   * `AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT` cap. The cap is per
   * message, not per conversation: the conversation Files tab is paginated
   * (`listByConversationId`), a message's own attachment strip is not.
   *
   * @since 1.0.0
   *
   * @param string $messageId the owning message identifier
   *
   * @return int the attachment count
   */
  public function countByMessageId(string $messageId): int;

  /**
   * Method listByConversationId.
   *
   * Lists a conversation's attachments, most recently uploaded first — the
   * conversation Files tab.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the owning conversation identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagingAttachmentPage the attachment page result
   */
  public function listByConversationId(string $conversationId, int $page, int $itemsPerPage): MessagingAttachmentPage;

  /**
   * Method delete.
   *
   * Deletes an attachment record.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentId $id the attachment identifier
   */
  public function delete(MessagingAttachmentId $id): void;
  // #endregion
}
