<?php

declare(strict_types=1);

namespace Webhook\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception WebhookDeliveryNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDeliveryNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the webhook delivery identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Webhook delivery with ID "%s" not found.', $id));
  }
  // #endregion
}
