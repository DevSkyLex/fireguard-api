<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\SearchOrganization;

use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentSearchPort, FacilitySearchPort, InspectionSearchPort, InterventionSearchPort, NonConformitySearchPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * UseCase SearchOrganizationHandler.
 *
 * Organization-wide global search across five result types, each owned by
 * another module and reached through its outbound search port (the
 * naming/statistics port pattern). Access follows
 * {@see \Organization\Application\UseCase\Query\Organization\GetNavigationCounters\GetNavigationCountersHandler}:
 * scope first (a non-member gets 404, never a hint the organization
 * exists), then each type is individually soft-gated on its own read
 * permission — a member without `organization.equipment.read` simply gets
 * an empty `equipments` list, never an error. Inspections and
 * non-conformities share `organization.inspection.read`, mirroring every
 * other surface of the Inspection module.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SearchOrganizationHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_RESULTS_PER_TYPE.
   *
   * The global search is a jump list, not a listing — each type contributes
   * at most this many rows, and the dedicated collection endpoints carry
   * the full result set.
   *
   * @since 1.0.0
   */
  private const int MAX_RESULTS_PER_TYPE = 5;

  /**
   * Constant MIN_TERM_LENGTH.
   *
   * @since 1.0.0
   */
  private const int MIN_TERM_LENGTH = 2;

  /**
   * Constant MAX_TERM_LENGTH.
   *
   * @since 1.0.0
   */
  private const int MAX_TERM_LENGTH = 100;

  /**
   * Constant EQUIPMENT_READ_PERMISSION.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string EQUIPMENT_READ_PERMISSION = 'organization.equipment.read';

  /**
   * Constant FACILITIES_READ_PERMISSION.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string FACILITIES_READ_PERMISSION = 'organization.facilities.read';

  /**
   * Constant INTERVENTIONS_READ_PERMISSION.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string INTERVENTIONS_READ_PERMISSION = 'organization.interventions.read';

  /**
   * Constant INSPECTION_READ_PERMISSION.
   *
   * Gates both `inspections` and `nonConformities` — the Inspection module
   * has a single read permission for its whole surface.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string INSPECTION_READ_PERMISSION = 'organization.inspection.read';
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
   * @param EquipmentSearchPort $equipmentSearch the equipment search port
   * @param FacilitySearchPort $facilitySearch the facility search port
   * @param InterventionSearchPort $interventionSearch the intervention search port
   * @param InspectionSearchPort $inspectionSearch the inspection search port
   * @param NonConformitySearchPort $nonConformitySearch the non-conformity search port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationAuthorizationPort $authorization,
    private EquipmentSearchPort $equipmentSearch,
    private FacilitySearchPort $facilitySearch,
    private InterventionSearchPort $interventionSearch,
    private InspectionSearchPort $inspectionSearch,
    private NonConformitySearchPort $nonConformitySearch,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param SearchOrganizationQuery $query the query value
   *
   * @throws InvalidArgumentException when the term is shorter than 2 or longer than 100 characters
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the user has no active membership
   *
   * @return SearchOrganizationResult the grouped search hits
   */
  public function __invoke(SearchOrganizationQuery $query): SearchOrganizationResult
  {
    $term = trim($query->term);
    $length = mb_strlen($term);
    if ($length < self::MIN_TERM_LENGTH || $length > self::MAX_TERM_LENGTH) {
      throw new InvalidArgumentException(sprintf(
        'The search term must be between %d and %d characters long.',
        self::MIN_TERM_LENGTH,
        self::MAX_TERM_LENGTH,
      ));
    }

    $organizationId = OrganizationId::fromString($query->organizationId);

    $organization = $this->organizationRepository->findById($organizationId);
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $query->userId);
    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($query->userId, $query->organizationId);
    }

    // One effective-permission resolution: the authorization service caches
    // the permission set per (user, organization), so the four checks below
    // cost a single lookup.
    $canReadInspections = $this->authorization->hasPermission($query->userId, $query->organizationId, self::INSPECTION_READ_PERMISSION);

    return new SearchOrganizationResult(
      equipments: $this->authorization->hasPermission($query->userId, $query->organizationId, self::EQUIPMENT_READ_PERMISSION)
        ? $this->equipmentSearch->search($query->organizationId, $term, self::MAX_RESULTS_PER_TYPE)
        : [],
      facilities: $this->authorization->hasPermission($query->userId, $query->organizationId, self::FACILITIES_READ_PERMISSION)
        ? $this->facilitySearch->search($query->organizationId, $term, self::MAX_RESULTS_PER_TYPE)
        : [],
      interventions: $this->authorization->hasPermission($query->userId, $query->organizationId, self::INTERVENTIONS_READ_PERMISSION)
        ? $this->interventionSearch->search($query->organizationId, $term, self::MAX_RESULTS_PER_TYPE)
        : [],
      inspections: $canReadInspections
        ? $this->inspectionSearch->search($query->organizationId, $term, self::MAX_RESULTS_PER_TYPE)
        : [],
      nonConformities: $canReadInspections
        ? $this->nonConformitySearch->search($query->organizationId, $term, self::MAX_RESULTS_PER_TYPE)
        : [],
    );
  }
  // #endregion
}
