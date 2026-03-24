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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreateOrganizationResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $ownerMemberId the owner member ID
   * @param string $ownerRoleId the owner role ID
   * @param string $name the organization name
   * @param string $slug the organization slug
   * @param string $ownerUserId the owner user ID
   * @param string $createdByUserId the creator user ID
   * @param string $status the organization status
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the update date
   */
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
