<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents;

use Audit\Application\Contract\OrganizationAuditEntry;
use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function array_key_exists;

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
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationAuditFeedPort $auditFeed,
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

    /** @var array<string, bool> $membershipCache */
    $membershipCache = [];
    $items = [];
    foreach ($result->items as $entry) {
      $items[] = $this->mapToResult($entry, $organizationId, $membershipCache);
    }

    return new PaginatedResult(
      items: $items,
      total: $result->total,
      limit: $result->limit,
      offset: $result->offset,
    );
  }

  /**
   * Method mapToResult.
   *
   * Maps one published ledger entry to the use case result, deciding whether
   * the actor may be named to this audience.
   *
   * @since 1.1.0
   *
   * @param OrganizationAuditEntry $entry the published ledger entry
   * @param OrganizationId $organizationId the organization being read
   * @param array<string, bool> $membershipCache per-invocation membership cache
   *
   * @return OrganizationAuditEventResult the result item
   */
  private function mapToResult(
    OrganizationAuditEntry $entry,
    OrganizationId $organizationId,
    array &$membershipCache,
  ): OrganizationAuditEventResult {
    return new OrganizationAuditEventResult(
      id: $entry->id,
      action: $entry->action,
      actorType: $entry->actorType,
      actorId: $entry->actorId,
      actorIsOrganizationMember: $this->actorIsOrganizationMember($entry, $organizationId, $membershipCache),
      subjectType: $entry->subjectType,
      subjectId: $entry->subjectId,
      metadata: $entry->metadata,
      occurredAt: $entry->occurredAt,
      recordedAt: $entry->recordedAt,
    );
  }

  /**
   * Method actorIsOrganizationMember.
   *
   * Decides whether an actor is nameable to this organization.
   *
   * A ledger row's actor is not necessarily one of the organization's people:
   * a platform operator acting on the organization is recorded here too, and
   * resolving their display name would disclose the identity of a user the
   * reader has no relationship with. So the answer is membership-based, not
   * "is there a user record": anyone with a membership row in THIS
   * organization (active or deactivated — a former colleague stays nameable,
   * otherwise the feed's history becomes anonymous the moment someone
   * leaves) is nameable, everyone else is not.
   *
   * Bounded by the page size (100), and cached per distinct actor within one
   * invocation.
   *
   * @since 1.1.0
   *
   * @param OrganizationAuditEntry $entry the published ledger entry
   * @param OrganizationId $organizationId the organization being read
   * @param array<string, bool> $cache per-invocation membership cache
   *
   * @return bool true when the actor may be named to this audience
   */
  private function actorIsOrganizationMember(
    OrganizationAuditEntry $entry,
    OrganizationId $organizationId,
    array &$cache,
  ): bool {
    if ('user' !== $entry->actorType || null === $entry->actorId) {
      return false;
    }

    if (array_key_exists($entry->actorId, $cache)) {
      return $cache[$entry->actorId];
    }

    $isMember = null !== $this->memberRepository->findByOrganizationAndUser($organizationId, $entry->actorId);
    $cache[$entry->actorId] = $isMember;

    return $isMember;
  }
  // #endregion
}
