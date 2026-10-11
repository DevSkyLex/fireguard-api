<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Attachment;

use DateTimeImmutable;

/**
 * Class MessagingAttachmentView
 *
 * Immutable attachment metadata, including the persisted concurrency revision.
 * Storage addressing remains internal to the owning use case.
 *
 * @category Contract
 */
final readonly class MessagingAttachmentView
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures a persisted attachment without exposing its aggregate or Record.
   *
   * @access public
   *
   * @param string $id attachment identifier
   * @param string $messageId owning message identifier
   * @param string $conversationId owning conversation identifier
   * @param string $organizationId owning organization identifier
   * @param string $uploadedByMemberId uploading member identifier
   * @param string $fileName original filename
   * @param string $mimeType stored media type
   * @param int $size stored byte count
   * @param ?string $label optional attachment label
   * @param DateTimeImmutable $uploadedAt upload timestamp
   * @param int $revision persisted mutation revision
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $messageId,
    public string $conversationId,
    public string $organizationId,
    public string $uploadedByMemberId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
    public int $revision = 1,
  ) {
  }
  // #endregion
}
