<?php

declare(strict_types=1);

namespace Approval\Domain\ValueObject;

use function array_column;

/**
 * Enum ApprovalStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ApprovalStatus: string
{
  case PENDING = 'pending';
  case APPROVED = 'approved';
  case REJECTED = 'rejected';
  case CANCELLED = 'cancelled';
  case EXPIRED = 'expired';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported approval status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the approval status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
