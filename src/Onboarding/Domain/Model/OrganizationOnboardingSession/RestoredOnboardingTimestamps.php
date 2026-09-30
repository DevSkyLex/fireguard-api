<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

use DateTimeImmutable;

/** Persisted session timestamps, including optional dismissal. */
final readonly class RestoredOnboardingTimestamps
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries creation, update and optional dismissal times for an onboarding session.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt session creation timestamp
   * @param DateTimeImmutable $updatedAt most recent session update timestamp
   * @param ?DateTimeImmutable $dismissedAt optional time when the user dismissed onboarding
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $dismissedAt = null,
  ) {
  }
  // #endregion
}
