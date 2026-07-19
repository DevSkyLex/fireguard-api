<?php

declare(strict_types=1);

namespace Webhook\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception WebhookSubscriptionNotFoundException.
 *
 * Also raised, mirroring {@see \Organization\Domain\Exception\TeamNotFoundException},
 * when a subscription exists but belongs to a different organization than
 * the one requested — information hiding, not a distinct access-denied case.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSubscriptionNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the webhook subscription identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Webhook subscription with ID "%s" not found.', $id));
  }
  // #endregion
}
