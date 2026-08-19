<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetNavigationCounters;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InterventionStatisticsPort, NonConformityStatisticsPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetNavigationCountersHandler.
 *
 * Resolves the sidebar navigation badge counters for one organization: the
 * number of open field interventions and the number of open (or
 * in-progress) non-conformities.
 *
 * Access mirrors `GetCurrentOrganizationMemberProfileHandler` (`GET
 * /organizations/{id}/me`): the caller only needs to be an ACTIVE member of
 * the organization, not a specific permission — sidebar badges are visible
 * to every member. Each counter is then individually soft-gated on the same
 * read permission its data section already requires elsewhere
 * (`organization.interventions.read` / `organization.inspection.read`),
 * mirroring how `GetOrganizationDashboardHandler` degrades
 * `recentInterventions`/`interventions` to an empty/zero value instead of a
 * 403 for a member without that specific permission — a badge a member
 * cannot otherwise see should read as "nothing to show", not fail the whole
 * request.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNavigationCountersHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant INTERVENTIONS_READ_PERMISSION.
   *
   * Soft-gates `openInterventions`, mirroring the dashboard's
   * `recentInterventions` section.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string INTERVENTIONS_READ_PERMISSION = 'organization.interventions.read';

  /**
   * Constant NON_CONFORMITIES_READ_PERMISSION.
   *
   * Soft-gates `openNonConformities`, mirroring the permission the
   * dashboard's non-conformity KPIs already require.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string NON_CONFORMITIES_READ_PERMISSION = 'organization.inspection.read';

  /**
   * Constant INTERVENTIONS_REVIEW_PERMISSION.
   *
   * Soft-gates `submittedInterventions` — the "to review" badge only means
   * something to a member who may actually review, so anyone else reads 0.
   *
   * @since 1.1.0
   *
   * @var string
   */
  private const string INTERVENTIONS_REVIEW_PERMISSION = 'organization.interventions.review';

  /**
   * Constant OPEN_NON_CONFORMITY_STATUSES.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array OPEN_NON_CONFORMITY_STATUSES = ['open', 'in_progress'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param InterventionStatisticsPort $interventionStatistics the intervention statistics service
   * @param NonConformityStatisticsPort $nonConformityStatistics the non-conformity statistics service
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationAuthorizationPort $authorization,
    private InterventionStatisticsPort $interventionStatistics,
    private NonConformityStatisticsPort $nonConformityStatistics,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetNavigationCountersQuery $query the query payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the user has no active membership
   *
   * @return GetNavigationCountersResult the resolved navigation counters
   */
  public function __invoke(GetNavigationCountersQuery $query): GetNavigationCountersResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);

    $organization = $this->organizationRepository->findById($organizationId);
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $query->userId);
    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($query->userId, $query->organizationId);
    }

    $openInterventions = $this->authorization->hasPermission($query->userId, $query->organizationId, self::INTERVENTIONS_READ_PERMISSION)
      ? $this->countOpenInterventions($query->organizationId)
      : 0;

    $openNonConformities = $this->authorization->hasPermission($query->userId, $query->organizationId, self::NON_CONFORMITIES_READ_PERMISSION)
      ? $this->countOpenNonConformities($query->organizationId)
      : 0;

    $submittedInterventions = $this->authorization->hasPermission($query->userId, $query->organizationId, self::INTERVENTIONS_REVIEW_PERMISSION)
      ? $this->interventionStatistics->countSubmitted($query->organizationId)
      : 0;

    return new GetNavigationCountersResult(
      openInterventions: $openInterventions,
      openNonConformities: $openNonConformities,
      submittedInterventions: $submittedInterventions,
    );
  }

  /**
   * Method countOpenInterventions.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the number of open interventions (excludes `published`/`abandoned`)
   */
  private function countOpenInterventions(string $organizationId): int
  {
    $overview = $this->interventionStatistics->countOverview($organizationId, new DateTimeImmutable('now'));

    return $overview['open'];
  }

  /**
   * Method countOpenNonConformities.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the number of non-conformities currently `open` or `in_progress`
   */
  private function countOpenNonConformities(string $organizationId): int
  {
    $countsByStatus = $this->nonConformityStatistics->countNonConformitiesByStatus($organizationId);

    $total = 0;
    foreach (self::OPEN_NON_CONFORMITY_STATUSES as $status) {
      $total += $countsByStatus[$status] ?? 0;
    }

    return $total;
  }
  // #endregion
}
