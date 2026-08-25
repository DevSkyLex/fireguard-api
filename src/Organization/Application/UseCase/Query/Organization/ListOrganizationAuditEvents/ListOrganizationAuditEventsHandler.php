<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents;

use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationAuditEntryProjector;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ListOrganizationAuditEventsHandler.
 *
 * Reads the organization's slice of the audit ledger through the Audit
 * module's published inbound capability
 * ({@see OrganizationAuditFeedPort}), which owns both the organization
 * scoping and the reduction of the payload — this handler owns the
 * authorization ordering and the membership-scoped actor resolution.
 *
 * @category Handler
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationAuditEventsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant AUDIT_READ_PERMISSION.
   *
   * Permission gating the organization activity feed.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string AUDIT_READ_PERMISSION = 'organization.audit.read';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationAuditEventsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization service
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationAuditFeedPort $auditFeed the Audit module's published organization feed
   * @param OrganizationAuditEntryProjector $projector the audience-safe entry projector, shared with the export
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationAuditFeedPort $auditFeed,
    private OrganizationAuditEntryProjector $projector,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Proves the organization exists and the caller is an active member
   * (both surface as 404, so an outsider cannot confirm the organization
   * exists), asserts the audit read permission (403 for an entitled-less
   * member), then returns the organization's audit events newest first.
   *
   * @since 1.0.0
   *
   * @param ListOrganizationAuditEventsQuery $query the query to handle
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the caller has no active membership
   * @throws OrganizationAccessDeniedException when the member lacks the audit read permission
   *
   * @return PaginatedResult<OrganizationAuditEventResult> the paginated, reduced events
   */
  public function __invoke(ListOrganizationAuditEventsQuery $query): PaginatedResult
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

    $this->authorization->assertGrantedPermissions(
      $query->userId,
      $query->organizationId,
      [self::AUDIT_READ_PERMISSION],
    );

    $result = $this->auditFeed->listForOrganization(
      organizationId: $query->organizationId,
      action: $query->action,
      from: $query->from,
      to: $query->to,
      pagination: $query->pagination,
    );

    $this->projector->reset();
    $items = [];
    foreach ($result->items as $entry) {
      $items[] = $this->projector->project($entry, $organizationId);
    }

    return new PaginatedResult(
      items: $items,
      total: $result->total,
      limit: $result->limit,
      offset: $result->offset,
    );
  }

  // #endregion
}
