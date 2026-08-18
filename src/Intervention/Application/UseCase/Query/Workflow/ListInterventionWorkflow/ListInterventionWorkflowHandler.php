<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow;

use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * UseCase ListInterventionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionWorkflowHandler implements QueryHandler
{
  // #region Constants
  /**
   * The client-facing value of the `due` filter that requests the overdue
   * population — the only value recognized today.
   *
   * @var string
   */
  private const string DUE_OVERDUE = 'overdue';
  // #endregion

  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionWorkflowHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param ClockPort $clock the clock port, resolving `now` for the `due=overdue` filter
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param ListInterventionWorkflowQuery $query the query value
   *
   * @return ListInterventionWorkflowResult the   invoke result
   */
  public function __invoke(ListInterventionWorkflowQuery $query): ListInterventionWorkflowResult
  {
    // For the `intervention` resource the scope id IS the organization id the
    // caller supplied; for every other resource it is an intervention id the
    // organization is resolved from. The two cases therefore need different
    // not-found messages below, though both must answer 404.
    $scopeIsOrganization = 'intervention' === $query->resource;
    $organizationId = $query->scopeId;
    if (!$scopeIsOrganization) {
      $context = $this->gateway->interventionContext($query->scopeId);
      if (null === $context) {
        throw InterventionNotFoundException::withId($query->scopeId);
      }
      $organizationId = $context->organizationId;
    }

    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw $scopeIsOrganization
        ? InterventionNotFoundException::forOrganizationScope($query->scopeId)
        : InterventionNotFoundException::withId($query->scopeId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionWorkflowResult($this->gateway->list(
      $query->resource,
      $query->scopeId,
      $this->resolveFilters($query->filters),
      $query->page,
      $query->itemsPerPage,
      $query->sorting,
    ));
  }

  /**
   * Method resolveFilters.
   *
   * Translates the client-facing `due=overdue` filter into the gateway's
   * mechanical, already-resolved filter keys — the "what counts as
   * overdue" decision (a status not in {@see InterventionStatus::closedValues()}
   * whose due date is before `now`) is a business rule and stays here,
   * alongside the identical rule `GetInterventionStatisticsHandler`'s
   * gateway applies, rather than being reinvented in the Infrastructure
   * query builder. `dueAtAfter`/`dueAtBefore`, supplied verbatim by the
   * caller, are left untouched and compose with the derived bounds below.
   *
   * @since 1.1.0
   *
   * @param array<string, mixed> $filters the raw filters value
   *
   * @return array<string, mixed> the resolved filters result
   */
  private function resolveFilters(array $filters): array
  {
    if (self::DUE_OVERDUE !== ($filters['due'] ?? null)) {
      return $filters;
    }
    unset($filters['due']);
    $filters['overdueAsOf'] = $this->clock->now();
    $filters['overdueExcludedStatuses'] = InterventionStatus::closedValues();

    return $filters;
  }
}
