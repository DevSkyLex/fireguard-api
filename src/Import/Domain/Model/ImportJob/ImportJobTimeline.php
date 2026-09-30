<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of an import job. */
final readonly class ImportJobTimeline
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures import-job creation, update, start, and completion timestamps.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt time the job was created
   * @param DateTimeImmutable $updatedAt time the job was last updated
   * @param ?DateTimeImmutable $startedAt time processing began, when started
   * @param ?DateTimeImmutable $completedAt time processing completed, when finished
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $startedAt,
    public ?DateTimeImmutable $completedAt,
  ) {
  }
  // #endregion
}
