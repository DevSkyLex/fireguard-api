<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use Assistant\Domain\ValueObject\AssistantMessageStatus;
use RuntimeException;

use function sprintf;

/**
 * Exception AssistantMessageIllegalStatusTransitionException.
 *
 * Raised by {@see \Assistant\Domain\Model\Message\AssistantMessage} when a
 * caller attempts a status transition outside the legal state machine
 * (`pending -> streaming -> complete|failed`, plus `pending -> failed`
 * directly) diagrammed on {@see AssistantMessageStatus}. This is what keeps a
 * Messenger retry from ever re-appending a second reply for an
 * already-`complete`/`failed` (terminal) message.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantMessageIllegalStatusTransitionException extends RuntimeException
{
  // #region Methods
  /**
   * Method forTransition.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantMessageStatus $from the current status
   * @param AssistantMessageStatus $to the attempted target status
   * @param string $messageId the assistant message identifier
   *
   * @return self the exception instance
   */
  public static function forTransition(AssistantMessageStatus $from, AssistantMessageStatus $to, string $messageId): self
  {
    return new self(sprintf(
      'Assistant message "%s" cannot transition from "%s" to "%s".',
      $messageId,
      $from->value,
      $to->value,
    ));
  }
  // #endregion
}
