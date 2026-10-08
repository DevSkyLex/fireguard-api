<?php

declare(strict_types=1);

namespace Customer\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Class CustomerHistory
 *
 * Keeps archival state, timestamps and revision together for customer lifecycle changes.
 * Persisted restoration retains these values without resetting creation history.
 *
 * @category ValueObject
 */
final readonly class CustomerHistory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the exact lifecycle state of a customer revision.
   *
   * @access public
   *
   * @param DateTimeImmutable|null $archivedAt the archival time, or null for active customers
   * @param DateTimeImmutable $createdAt the original creation time
   * @param DateTimeImmutable $updatedAt the most recent mutation time
   * @param int $revision the retained concurrency revision
   *
   * @return void
   */
  public function __construct(
    public ?DateTimeImmutable $archivedAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public int $revision,
  ) {
  }
  // #endregion
}
