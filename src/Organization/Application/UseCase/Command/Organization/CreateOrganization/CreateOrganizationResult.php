<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganization;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateOrganizationResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $ownerMemberId,
    public string $ownerRoleId,
    public string $name,
    public string $slug,
    public string $ownerUserId,
    public string $createdByUserId,
    public string $status,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
