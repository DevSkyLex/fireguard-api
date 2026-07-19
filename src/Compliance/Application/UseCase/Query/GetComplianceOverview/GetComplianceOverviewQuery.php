<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetComplianceOverview;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetComplianceOverviewQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetComplianceOverviewQuery implements QueryMessage
{
  /**
   * Constructor.
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
}
