<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\ExportInterventions;

use Intervention\Application\Contract\Export\{InterventionExportCandidate, InterventionExportRow};
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionSiteNamingPort, InterventionWorkflowGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionExportTooLargeException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\ClockPort;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * UseCase ExportInterventionsHandler.
 *
 * Bounds the export before resolving a single display name: a cheap COUNT
 * against the same filters {@see \Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\ListInterventionWorkflowHandler}
 * applies for the `intervention` resource, rejecting the request with
 * {@see InterventionExportTooLargeException} when it exceeds
 * {@see self::MAX_EXPORT_ROWS} — mirrors `Audit\...\ExportAuditEventsHandler`.
 * Under the cap, the matching rows are fetched once and the site/responsible
 * display names are resolved in two bulk round trips (never one query per
 * row), exactly like {@see \Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics\GetInterventionStatisticsHandler}
 * resolves its `bySite`/`byResponsible` breakdown names.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInterventionsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of interventions a single export request may
   * match, mirroring `Audit\...\ExportAuditEventsHandler::MAX_EXPORT_ROWS`.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_EXPORT_ROWS = 50_000;

  /**
   * The client-facing value of the `due` filter that requests the overdue
   * population, mirroring {@see \Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\ListInterventionWorkflowHandler::DUE_OVERDUE}.
   *
   * @var string
   */
  private const string DUE_OVERDUE = 'overdue';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the workflow gateway port
   * @param InterventionSiteNamingPort $siteNaming the site naming port
   * @param InterventionMemberNamingPort $memberNaming the member naming port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param ClockPort $clock the clock port, resolving `now` for the `due=overdue` filter
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private InterventionSiteNamingPort $siteNaming,
    private InterventionMemberNamingPort $memberNaming,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExportInterventionsQuery $query the query to handle
   *
   * @throws InterventionNotFoundException when the caller is outside the organization's scope
   * @throws InterventionAccessDeniedException when the caller lacks `organization.interventions.read`
   * @throws InterventionExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} interventions
   *
   * @return ExportInterventionsResult the bounded, name-resolved export result
   */
  public function __invoke(ExportInterventionsQuery $query): ExportInterventionsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    $filters = $this->resolveFilters($query->filters);

    $total = $this->gateway->countInterventions($query->organizationId, $filters);
    if ($total > self::MAX_EXPORT_ROWS) {
      throw InterventionExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $candidates = $this->gateway->listInterventionExportCandidates($query->organizationId, $filters);

    $siteNames = $this->siteNaming->findNamesByIds($query->organizationId, $this->uniqueIds($candidates, static fn (InterventionExportCandidate $candidate): ?string => $candidate->siteId));
    $responsibleNames = $this->memberNaming->displayNamesFor($query->organizationId, $this->uniqueIds($candidates, static fn (InterventionExportCandidate $candidate): ?string => $candidate->responsibleId));

    $rows = array_map(
      static fn (InterventionExportCandidate $candidate): InterventionExportRow => new InterventionExportRow(
        id: $candidate->id,
        name: $candidate->name,
        type: $candidate->type,
        status: $candidate->status,
        priority: $candidate->priority,
        siteId: $candidate->siteId,
        siteName: null === $candidate->siteId ? null : ($siteNames[$candidate->siteId] ?? null),
        responsibleId: $candidate->responsibleId,
        responsibleName: null === $candidate->responsibleId ? null : ($responsibleNames[$candidate->responsibleId] ?? null),
        dueAt: $candidate->dueAt,
        createdAt: $candidate->createdAt,
        updatedAt: $candidate->updatedAt,
      ),
      $candidates,
    );

    return new ExportInterventionsResult($rows, $total);
  }

  /**
   * Method resolveFilters.
   *
   * Translates the client-facing `due=overdue` filter into the gateway's
   * mechanical, already-resolved filter keys — duplicated deliberately from
   * {@see \Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\ListInterventionWorkflowHandler::resolveFilters()}
   * (a third occurrence of the same small rule, alongside `GetInterventionStatisticsHandler`'s
   * gateway) rather than factored out, since each handler owns its query
   * independently and the rule is a few lines of mechanical translation, not
   * business logic that could drift.
   *
   * @since 1.0.0
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

  /**
   * Method uniqueIds.
   *
   * @since 1.0.0
   *
   * @param list<InterventionExportCandidate> $candidates the candidates value
   * @param callable(InterventionExportCandidate): ?string $extract the field extractor
   *
   * @return list<string> the unique, non-null identifiers
   */
  private function uniqueIds(array $candidates, callable $extract): array
  {
    return array_values(array_unique(array_filter(array_map($extract, $candidates), static fn (?string $id): bool => null !== $id)));
  }
  // #endregion
}
