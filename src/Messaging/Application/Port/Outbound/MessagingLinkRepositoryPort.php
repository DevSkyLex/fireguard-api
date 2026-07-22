<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\MessagingLinkPage;

/**
 * Port MessagingLinkRepositoryPort.
 *
 * URLs extracted from message bodies (B2) — persisted alongside, never part
 * of, the `Message` aggregate itself (mirrors reactions/saved messages:
 * `messaging_message_link` is a pure satellite table with no domain
 * aggregate, see `MODULE.md`'s "Domain Model").
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingLinkRepositoryPort
{
  // #region Methods
  /**
   * Method replaceForMessage.
   *
   * Replaces every link previously extracted from the given message with
   * the given (already deduplicated) URL list — a plain `DELETE` scoped to
   * the message followed by a bulk `INSERT`, never a load-modify-save
   * cycle. Called on BOTH message creation (no existing rows, a no-op
   * delete) and edit (replaces the prior extraction), so callers never need
   * to branch on create vs. edit. An empty `$urls` list clears every link
   * previously extracted from the message.
   *
   * @since 1.0.0
   *
   * @param string $messageId the owning message identifier
   * @param string $conversationId the owning conversation identifier
   * @param list<string> $urls the deduplicated URLs extracted from the message body
   * @param DateTimeImmutable $extractedAt the extraction date (the message's creation/edit date)
   */
  public function replaceForMessage(string $messageId, string $conversationId, array $urls, DateTimeImmutable $extractedAt): void;

  /**
   * Method listByConversation.
   *
   * Lists a conversation's extracted links, newest first — the conversation
   * Links tab.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the owning conversation identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagingLinkPage the link page result
   */
  public function listByConversation(string $conversationId, int $page, int $itemsPerPage): MessagingLinkPage;
  // #endregion
}
