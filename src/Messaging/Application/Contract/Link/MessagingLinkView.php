<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Link;

use DateTimeImmutable;

/**
 * Contract MessagingLinkView.
 *
 * Read model for a URL extracted from a message body (B2).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingLinkView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the link identifier
   * @param string $messageId the owning message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $url the extracted URL
   * @param ?string $label an optional display label, currently never populated by extraction — reserved for a future link-preview feature
   * @param DateTimeImmutable $createdAt the extraction date (the message's creation/edit date)
   */
  public function __construct(
    public string $id,
    public string $messageId,
    public string $conversationId,
    public string $url,
    public ?string $label,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
