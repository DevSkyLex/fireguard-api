<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use DateTimeImmutable;
use Procurement\Domain\Exception\ProcurementException;

/**
 * Class SupplierHistory
 *
 * Restores one coherent lifecycle and optimistic revision without inventing timestamps.
 *
 * @category ValueObject
 */
final readonly class SupplierHistory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ?DateTimeImmutable $archivedAt the retained archivedAt
   * @param DateTimeImmutable $createdAt the retained createdAt
   * @param DateTimeImmutable $updatedAt the retained updatedAt
   * @param int $revision the retained revision
   *
   * @return void
   */
  public function __construct(public ?DateTimeImmutable $archivedAt, public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt, public int $revision)
  {
    if ($revision < 1 || $updatedAt < $createdAt || (null !== $archivedAt && ($archivedAt < $createdAt || $archivedAt > $updatedAt))) {
      throw ProcurementException::invalid('Supplier revision and historical timestamps are inconsistent.');
    }
  }
  // #endregion
}
