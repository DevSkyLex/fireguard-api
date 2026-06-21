<?php

declare(strict_types=1);

namespace Billing\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject SubscriptionId.
 *
 * Stable identifier of a billing subscription aggregate.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SubscriptionId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a SubscriptionId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the subscription identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
