<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $id,
    public string $name,
    public string $slug,
    public string $ownerUserId,
    public string $createdByUserId,
    public string $status,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
