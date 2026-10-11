<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess;

use Inspection\Application\Port\Outbound\InspectionReportEntitlementPort;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException, InspectionReportNotEntitledException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/** Authorizes report scope and the published PDF entitlement.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionReportAccessHandler implements QueryHandler
{
  public function __construct(private OrganizationAuthorizationPort $authorization, private InspectionReportEntitlementPort $entitlement)
  {
  }

  public function __invoke(ResolveInspectionReportAccessQuery $query): ResolveInspectionReportAccessResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw new InspectionNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.read permission.');
    }
    if (!$this->entitlement->isExportEntitled($query->organizationId)) {
      throw InspectionReportNotEntitledException::planTooLow($query->organizationId);
    }

    return new ResolveInspectionReportAccessResult($this->entitlement->resolvePlanKey($query->organizationId) ?? 'unknown');
  }
}
