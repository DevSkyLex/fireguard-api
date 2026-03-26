<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics;

use DateInterval;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InspectionStatisticsPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationInspectionStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInspectionStatisticsHandler implements QueryHandler
{
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private InspectionStatisticsPort $inspectionStatistics,
  ) {
  }

  public function __invoke(GetOrganizationInspectionStatisticsQuery $query): GetOrganizationInspectionStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.inspection.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.inspection.read');
    }

    $countsByStatus = $this->inspectionStatistics->countInspectionsByStatus($query->organizationId);
    $countsByResult = $this->inspectionStatistics->countInspectionsByResult($query->organizationId);
    $countsByInspectorType = $this->inspectionStatistics->countInspectionsByInspectorType($query->organizationId);
    $now = new DateTimeImmutable();

    return new GetOrganizationInspectionStatisticsResult(
      totalCount: $this->inspectionStatistics->countInspections($query->organizationId),
      draftCount: $countsByStatus['draft'] ?? 0,
      submittedCount: $countsByStatus['submitted'] ?? 0,
      closedCount: $countsByStatus['closed'] ?? 0,
      passCount: $countsByResult['pass'] ?? 0,
      failCount: $countsByResult['fail'] ?? 0,
      partialCount: $countsByResult['partial'] ?? 0,
      countsByInspectorType: $countsByInspectorType,
      performedLast7DaysCount: $this->inspectionStatistics->countInspectionsPerformedSince(
        $query->organizationId,
        $now->sub(new DateInterval('P7D'))->format('c'),
      ),
      performedLast30DaysCount: $this->inspectionStatistics->countInspectionsPerformedSince(
        $query->organizationId,
        $now->sub(new DateInterval('P30D'))->format('c'),
      ),
    );
  }
}
