<?php

declare(strict_types=1);

namespace Webhook\Domain\ValueObject;

use function array_column;

/**
 * Enum WebhookDeliveryStatus.
 *
 * The lifecycle status of a {@see \Webhook\Domain\Model\Delivery\WebhookDelivery}:
 * `pending` while queued or awaiting a retry, `delivered` once a 2xx
 * response is received, `failed` once the maximum attempt count is
 * exhausted (terminal).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum WebhookDeliveryStatus: string
{
  case PENDING = 'pending';
  case DELIVERED = 'delivered';
  case FAILED = 'failed';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported delivery status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the delivery status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isTerminal.
   *
   * Reports whether the status is a terminal (final) state.
   *
   * @since 1.0.0
   *
   * @return bool true when the delivery can no longer transition
   */
  public function isTerminal(): bool
  {
    return self::DELIVERED === $this || self::FAILED === $this;
  }
  // #endregion
}
