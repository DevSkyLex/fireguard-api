<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Class ServiceRequestTimeline
 *
 * Retains every lifecycle decision instant independently, including prior qualification history.
 *
 * @category ValueObject
 */
final readonly class ServiceRequestTimeline
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param DateTimeImmutable $requestedAt original request creation instant
   * @param DateTimeImmutable $updatedAt latest persisted change instant
   * @param DateTimeImmutable|null $qualifiedAt original qualification instant
   * @param DateTimeImmutable|null $rejectedAt explicit rejection instant
   * @param DateTimeImmutable|null $cancelledAt explicit cancellation instant
   * @param DateTimeImmutable|null $convertedAt committed work conversion instant
   *
   * @return void
   */
  public function __construct(public DateTimeImmutable $requestedAt, public DateTimeImmutable $updatedAt, public ?DateTimeImmutable $qualifiedAt = null, public ?DateTimeImmutable $rejectedAt = null, public ?DateTimeImmutable $cancelledAt = null, public ?DateTimeImmutable $convertedAt = null)
  {
  }
  // #endregion
}
