<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationSlug, OrganizationStatus};

/** Optional persisted state with the same legacy fallbacks as the aggregate. */
final readonly class RestoredOrganizationState
{
  public function __construct(
    public ?DateTimeImmutable $updatedAt = null,
    public ?string $ownerUserId = null,
    public ?OrganizationSlug $slug = null,
    public ?OrganizationStatus $status = null,
  ) {
  }
}
