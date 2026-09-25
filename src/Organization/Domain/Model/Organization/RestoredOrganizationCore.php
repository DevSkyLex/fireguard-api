<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};

/** Persisted organization identity and original active-state fallback. */
final readonly class RestoredOrganizationCore
{
  public function __construct(
    public OrganizationId $id,
    public OrganizationName $name,
    public string $createdByUserId,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
