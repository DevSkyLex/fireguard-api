<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;

/** Persisted invitation creation and last update times. */
final readonly class RestoredInvitationTimestamps
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
