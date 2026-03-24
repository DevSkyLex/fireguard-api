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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the organization ID
   * @param string $name the organization name
   * @param string $slug the organization slug
   * @param string $ownerUserId the owner user ID
   * @param string $createdByUserId the creator user ID
   * @param string $status the organization status
   * @param bool $isActive whether the organization is active
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the update date
   * @param int $memberCount the member count
   */
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
    public int $memberCount = 0,
  ) {
  }
  // #endregion
}
