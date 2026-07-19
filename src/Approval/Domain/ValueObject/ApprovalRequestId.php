<?php

declare(strict_types=1);

namespace Approval\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject ApprovalRequestId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalRequestId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Creates an ApprovalRequestId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the approval request identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
