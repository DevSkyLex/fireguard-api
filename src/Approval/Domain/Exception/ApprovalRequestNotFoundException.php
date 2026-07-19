<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApprovalRequestNotFoundException.
 *
 * Also raised when a request exists but belongs to a different organization
 * than the one requested — information hiding, not a distinct access-denied
 * case (mirrors {@see \Webhook\Domain\Exception\WebhookSubscriptionNotFoundException}).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the approval request identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Approval request with ID "%s" not found.', $id));
  }
  // #endregion
}
