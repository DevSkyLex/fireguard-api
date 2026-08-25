<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents;

use Audit\Application\Contract\OrganizationAuditEntry;
use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationAuditEntryProjector;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ExportOrganizationAuditEventsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportOrganizationAuditEventsHandler implements QueryHandler
{
  // #region Constants
  /**
   * The permission this use case requires.
   *
   * Separate from `organization.audit.read` on purpose: reading the feed keeps
   * the data inside the product, exporting it takes a file out. Someone
   * entitled to look is not automatically entitled to walk away with a copy.
   *
   * @since 1.0.0
   */
  private const string AUDIT_EXPORT_PERMISSION = 'organization.audit.export';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationAuditFeedPort $auditFeed the organization-scoped audit feed
   * @param OrganizationAuditEntryProjector $projector the audience-safe entry projector
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
   * Exports the organization's slice of the audit ledger.
   *
   * The three checks below run in the same order as the list use case, and for
   * the same reason: an outsider must not be able to tell a real organization
   * from a fake one, so a missing organization and a non-membership both end as
   * the same 404 upstream.
   *
   * @since 1.0.0
   *
   * @param ExportOrganizationAuditEventsQuery $query the query to handle
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the caller is not an active member
   *
   * @return ExportOrganizationAuditEventsResult the streamable rows
   */
  public function __invoke(ExportOrganizationAuditEventsQuery $query): ExportOrganizationAuditEventsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);

    if (null === $this->organizationRepository->findById($organizationId)) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $query->userId);
    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($query->userId, $query->organizationId);
    }

    $this->authorization->assertGrantedPermissions(
      $query->userId,
      $query->organizationId,
      [self::AUDIT_EXPORT_PERMISSION],
    );

    // The cap is enforced inside this call, before any row is read, so an
    // over-large export fails here rather than halfway through a download.
    $entries = $this->auditFeed->exportForOrganization(
      organizationId: $query->organizationId,
      action: $query->action,
      from: $query->from,
      to: $query->to,
    );

    $this->projector->reset();

    return new ExportOrganizationAuditEventsResult(
      rows: $this->projectAll($entries, $organizationId),
    );
  }

  /**
   * Method projectAll.
   *
   * @since 1.0.0
   *
   * @param iterable<OrganizationAuditEntry> $entries the published ledger entries
   * @param OrganizationId $organizationId the organization being exported
   *
   * @return iterable<\Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\OrganizationAuditEventResult>
   */
  private function projectAll(iterable $entries, OrganizationId $organizationId): iterable
  {
    foreach ($entries as $entry) {
      yield $this->projector->project($entry, $organizationId);
    }
  }
  // #endregion
}
