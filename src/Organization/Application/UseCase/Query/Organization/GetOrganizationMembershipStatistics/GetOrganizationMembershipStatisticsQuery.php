<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationMembershipStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMembershipStatisticsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationMembershipStatisticsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the authenticated user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
  ) {
  }
  // #endregion
}
