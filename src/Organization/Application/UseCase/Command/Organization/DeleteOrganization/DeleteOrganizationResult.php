<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganization;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteOrganizationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public DateTimeImmutable $deletedAt,
  ) {
  }
  // #endregion
}
