<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use Assistant\Domain\ValueObject\AssistantMessageStatus;

/** Persisted content and generation result of an assistant message. */
final readonly class RestoredAssistantMessageContent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores an assistant message body together with its processing status and generation metadata.
   *
   * @access public
   *
   * @param string $body persisted message text
   * @param AssistantMessageStatus $status message generation or delivery status
   * @param ?string $errorCode failure code recorded for an unsuccessful message, when present
   * @param ?int $tokenCount token usage recorded for the generated content, when available
   *
   * @return void
   */
  public function __construct(
    public string $body,
    public AssistantMessageStatus $status,
    public ?string $errorCode,
    public ?int $tokenCount,
  ) {
  }
  // #endregion
}
