<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;
use Organization\Domain\ValueObject\OrganizationInvitationStatus;

/** Persisted invitation status and the matching actor/timestamp pairs. */
final readonly class RestoredInvitationLifecycle
{
  public function __construct(
    public OrganizationInvitationStatus $status,
    public DateTimeImmutable $expiresAt,
    public ?DateTimeImmutable $acceptedAt = null,
    public ?string $acceptedByUserId = null,
    public ?DateTimeImmutable $revokedAt = null,
    public ?string $revokedByUserId = null,
  ) {
  }
}
