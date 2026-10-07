<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Cost\GetMaintenanceCost;

use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceCostProjection};
use Shared\Application\Message\QueryHandler;

/** Class GetMaintenanceCostHandler. Requires the dedicated financial read permission. @category UseCase */
final readonly class GetMaintenanceCostHandler implements QueryHandler
{
  public function __construct(private MaintenanceCostAccessGuard $access, private MaintenanceCostProjection $projection)
  {
  }

  public function __invoke(GetMaintenanceCostQuery $query): GetMaintenanceCostResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, false);

    $view = $this->projection->view($query->organizationId, $query->interventionId);

    return new GetMaintenanceCostResult(new MaintenanceCostView($view->interventionId, $view->organizationId, $view->currency, $view->planning, $view->current, $view->frozen, $view->planningEditable && $this->access->canManage($query->actorId, $query->organizationId)));
  }
}
