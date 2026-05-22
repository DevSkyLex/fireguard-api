<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCurrentOrganizationMemberProfileQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentOrganizationMemberProfileQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentOrganizationMemberProfileQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $userId the authenticated user ID
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
  ) {
  }
  // #endregion
}
