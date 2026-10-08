<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;

/**
 * Class PurchaseOrderHistory
 *
 * Restores one coherent lifecycle and optimistic revision without inventing timestamps.
 *
 * @category ValueObject
 */
final readonly class PurchaseOrderHistory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param PurchaseOrderStatus $status the retained status
   * @param int $revision the retained revision
   * @param DateTimeImmutable $createdAt the retained createdAt
   * @param DateTimeImmutable $updatedAt the retained updatedAt
   *
   * @return void
   */
  public function __construct(public PurchaseOrderStatus $status, public int $revision, public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt)
  {
    if ($revision < 1 || $updatedAt < $createdAt) {
      throw ProcurementException::invalid('Purchase-order revision and historical timestamps are inconsistent.');
    }
  }
  // #endregion
}
