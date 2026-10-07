<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use function array_column;

/**
 * Enum PurchaseOrderStatus
 *
 * Describes gross reception progress independently from supplier returns.
 *
 * @category ValueObject
 */
enum PurchaseOrderStatus: string
{
  /** Case DRAFT */
  case DRAFT = 'draft';

  /** Case ORDERED */
  case ORDERED = 'ordered';

  /** Case PARTIAL_RECEIVED */
  case PARTIAL_RECEIVED = 'partial_received';

  /** Case RECEIVED */
  case RECEIVED = 'received';

  /** Case CANCELLED */
  case CANCELLED = 'cancelled';

  // #region Methods
  /**
   * Method values
   *
   * @access public
   *
   * @return list<string> the stable lifecycle codes
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method allowsReceipt
   *
   * Cancelled remaining quantities cannot silently become receivable again.
   *
   * @access public
   *
   * @return bool whether new quantities may be received
   */
  public function allowsReceipt(): bool
  {
    return self::ORDERED === $this || self::PARTIAL_RECEIVED === $this;
  }
  // #endregion
}
