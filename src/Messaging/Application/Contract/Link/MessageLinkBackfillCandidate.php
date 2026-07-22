<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Link;

use DateTimeImmutable;

/**
 * Contract MessageLinkBackfillCandidate.
 *
 * Minimal message projection used by the resumable historical-link backfill.
 */
final readonly class MessageLinkBackfillCandidate
{
  /**
   * @param string $messageId the message identifier and pagination cursor
   * @param string $conversationId the owning conversation identifier
   * @param string $body the retained sanitized body
   * @param DateTimeImmutable $extractedAt the latest message update timestamp
   * @param bool $isDeleted whether the message is tombstoned and must expose no links
   */
  public function __construct(
    public string $messageId,
    public string $conversationId,
    public string $body,
    public DateTimeImmutable $extractedAt,
    public bool $isDeleted,
  ) {
  }
}
