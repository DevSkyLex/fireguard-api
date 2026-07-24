<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Reaction;

use DateTimeImmutable;

/**
 * Contract MessageReactionView.
 *
 * Read model for a single persisted `messaging_reactions` row: one member's
 * reaction to one message with one emoji. Aggregation by emoji (count,
 * `reactedByMe`) happens at the Presentation layer
 * (`MessageOutputFactory`), never here — this is the flat, unaggregated read
 * model, mirroring how `MessagingAttachmentRepositoryPort::findByMessageIds()`
 * returns a flat list grouped by the caller.
 *
 * @category Contract
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessageReactionView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param string $messageId the owning message identifier
   * @param string $memberId the reacting member's identifier
   * @param string $emoji the reaction emoji
   * @param DateTimeImmutable $createdAt the reaction date
   */
  public function __construct(
    public string $messageId,
    public string $memberId,
    public string $emoji,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
