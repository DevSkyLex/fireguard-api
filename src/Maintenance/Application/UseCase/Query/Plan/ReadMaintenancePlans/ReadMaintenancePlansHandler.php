<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans;

use Intervention\Application\Port\Inbound\InterventionMaintenanceWorkPort;
use Maintenance\Application\Contract\Plan\{MaintenancePlanDetails, MaintenancePlanState};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Maintenance\Domain\Model\MaintenancePlan;
use Maintenance\Domain\ValueObject\{MaintenanceOperationKind, PlanCadence};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function in_array;
use function max;
use function min;

/** Scope-aware reads and date previews; reads never bootstrap or activate plans. */
final readonly class ReadMaintenancePlansHandler implements QueryHandler
{
  public function __construct(private MaintenancePlanStorePort $plans, private InterventionMaintenanceWorkPort $work, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(ReadMaintenancePlansQuery $query): ReadMaintenancePlansResult
  {
    $decision = $this->authorization->resolveAccess($query->actorUserId, $query->organizationId, 'organization.maintenance.read');
    if ($decision->isOutsideScope()) {
      throw MaintenanceNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new MaintenanceAccessDeniedException('Missing permission: organization.maintenance.read');
    }
    if (null !== $query->operationKind && !in_array($query->operationKind, ['control', 'maintenance'], true)) {
      throw new MaintenanceValidationException('Unknown maintenance operation kind.');
    }
    $mode = $this->plans->engineMode($query->organizationId);
    if ('engine' === $query->action) {
      return new ReadMaintenancePlansResult(mode: $mode, preparedCount: $this->plans->count($query->organizationId));
    }
    if (null !== $query->planId) {
      $plan = $this->plans->find($query->organizationId, $query->planId) ?? throw MaintenanceNotFoundException::withId($query->planId);
      $model = MaintenancePlan::reconstitute($plan->id, $plan->organizationId, $plan->equipmentId, $plan->name, MaintenanceOperationKind::from($plan->operationKind), 'legacy' === $plan->cadenceMode ? PlanCadence::legacyFromString($plan->interval) : PlanCadence::fromString($plan->interval), $plan->anchorAt, $plan->nextDueAt, $plan->createdAt, 'legacy' === $plan->cadenceMode, null !== $plan->archivedAt);

      return new ReadMaintenancePlansResult(items: [$this->details($plan)], total: 1, dates: 'preview' === $query->action ? $model->preview() : [], mode: $mode);
    }
    $size = max(1, min(100, $query->itemsPerPage));
    $offset = (max(1, $query->page) - 1) * $size;
    $items = $this->plans->list($query->organizationId, $size, $offset, $query->equipmentId, $query->operationKind, $query->search);

    return new ReadMaintenancePlansResult(items: array_map($this->details(...), $items), total: $this->plans->count($query->organizationId, $query->equipmentId, $query->operationKind, $query->search), mode: $mode);
  }

  private function details(MaintenancePlanState $plan): MaintenancePlanDetails
  {
    $open = $this->plans->openOccurrence($plan->organizationId, $plan->id);
    $retryAllowed = null !== $open && 'open' === $open->status && null !== $open->interventionId && (null !== $open->resultId || in_array($this->work->status($plan->organizationId, $open->interventionId), ['abandoned', 'published'], true));

    return new MaintenancePlanDetails($plan, $open, $retryAllowed);
  }
}
