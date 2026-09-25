<?php

declare(strict_types=1);

namespace Authorization\Domain\Model\Role;

use Authorization\Domain\Model\Permission\Permission;
use DateTimeImmutable;
use Shared\Domain\ValueObject\TenantId;

/** Metadata and permission assignments loaded with a persisted role. */
final readonly class RestoredRoleState
{
  /**
   * @param array<Permission> $permissions
   */
  public function __construct(
    public string $description,
    public bool $isSystem,
    public ?TenantId $tenantId,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $updatedAt,
    public array $permissions = [],
  ) {
  }
}
