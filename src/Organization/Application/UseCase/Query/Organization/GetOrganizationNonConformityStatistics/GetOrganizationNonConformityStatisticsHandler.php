<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationNonConformityStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationNonConformityStatisticsHandler implements QueryHandler
{
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private NonConformityStatisticsPort $nonConformityStatistics,
  ) {
  }

  public function __invoke(GetOrganizationNonConformityStatisticsQuery $query): GetOrganizationNonConformityStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.inspection.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.inspection.read');
    }

    $countsByStatus = $this->nonConformityStatistics->countNonConformitiesByStatus($query->organizationId);
    $countsBySeverity = $this->nonConformityStatistics->countNonConformitiesBySeverity($query->organizationId);

    return new GetOrganizationNonConformityStatisticsResult(
      totalCount: $this->nonConformityStatistics->countNonConformities($query->organizationId),
      openCount: $countsByStatus['open'] ?? 0,
      inProgressCount: $countsByStatus['in_progress'] ?? 0,
      doneCount: $countsByStatus['done'] ?? 0,
      waivedCount: $countsByStatus['waived'] ?? 0,
      lowSeverityCount: $countsBySeverity['low'] ?? 0,
      mediumSeverityCount: $countsBySeverity['medium'] ?? 0,
      highSeverityCount: $countsBySeverity['high'] ?? 0,
      criticalSeverityCount: $countsBySeverity['critical'] ?? 0,
    );
  }
}
