<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;

/** Persisted invitation creation and last update times. */
final readonly class RestoredInvitationTimestamps
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries creation and last-update times for a persisted invitation.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt invitation creation timestamp
   * @param DateTimeImmutable $updatedAt most recent invitation update timestamp
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
