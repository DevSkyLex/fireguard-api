<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionStatus;

/** Persisted lifecycle fields supplied alongside the intervention definition. */
final readonly class InterventionRestoredState
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Groups the persisted lifecycle and timestamps with an intervention definition.
   *
   * @access public
   *
   * @param InterventionCreation $creation creation data for the intervention
   * @param InterventionStatus $status persisted lifecycle status
   * @param ?string $reviewNote optional review note retained with the intervention
   * @param int $revision current optimistic-lock revision
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param DateTimeImmutable $updatedAt most recent update timestamp
   *
   * @return void
   */
  public function __construct(
    public InterventionCreation $creation,
    public InterventionStatus $status,
    public ?string $reviewNote,
    public int $revision,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
