<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase TransferOrganizationOwnershipResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TransferOrganizationOwnershipResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TransferOrganizationOwnershipResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $previousOwnerUserId the user ID of the previous owner
   * @param string $newOwnerUserId the user ID of the new owner
   * @param DateTimeImmutable $transferredAt the ownership transfer timestamp
   */
  public function __construct(
    public string $organizationId,
    public string $previousOwnerUserId,
    public string $newOwnerUserId,
    public DateTimeImmutable $transferredAt,
  ) {
  }
  // #endregion
}
