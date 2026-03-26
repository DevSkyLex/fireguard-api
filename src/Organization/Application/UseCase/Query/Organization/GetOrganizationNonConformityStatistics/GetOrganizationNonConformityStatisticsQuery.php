<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationNonConformityStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationNonConformityStatisticsQuery implements QueryMessage
{
  public function __construct(
    public string $organizationId,
    public string $userId,
  ) {
  }
}
