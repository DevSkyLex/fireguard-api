<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Audit\Application\Contract\OrganizationAuditEntry;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\OrganizationAuditEventResult;
use Organization\Domain\ValueObject\OrganizationId;

use function array_key_exists;

/**
 * Service OrganizationAuditEntryProjector.
 *
 * Projects a published ledger entry to the shape the organization audience is
 * allowed to see.
 *
 * This lives in one place because it encodes a privacy rule, and a privacy rule
 * duplicated across the list and the export is a privacy rule that will
 * eventually differ between them.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuditEntryProjector
{
  // #region Properties
  /**
   * Nameability answers, keyed by actor id, for one projection run.
   *
   * @var array<string, bool>
   */
  private array $cache = [];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
  public function __construct(
    private readonly OrganizationMemberRepositoryPort $memberRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method project.
   *
   * Maps one published ledger entry to the result shape.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuditEntry $entry the published ledger entry
   * @param OrganizationId $organizationId the organization being read
   *
   * @return OrganizationAuditEventResult the projected entry
   */
  public function project(OrganizationAuditEntry $entry, OrganizationId $organizationId): OrganizationAuditEventResult
  {
    return new OrganizationAuditEventResult(
      id: $entry->id,
      action: $entry->action,
      actorType: $entry->actorType,
      actorId: $entry->actorId,
      actorIsOrganizationMember: $this->actorIsOrganizationMember($entry, $organizationId),
      subjectType: $entry->subjectType,
      subjectId: $entry->subjectId,
      metadata: $entry->metadata,
      occurredAt: $entry->occurredAt,
      recordedAt: $entry->recordedAt,
    );
  }

  /**
   * Method reset.
   *
   * Clears the nameability cache between projection runs.
   *
   * @since 1.0.0
   */
  public function reset(): void
  {
    $this->cache = [];
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
   * otherwise the feed's history becomes anonymous the moment someone leaves)
   * is nameable, everyone else is not.
   *
   * Cached per distinct actor for the run. On a page that is at most 100 rows;
   * on an export it is at most the export cap, but keyed by actor rather than
   * by row, and an organization's ledger is written by a small, bounded set of
   * people — so the cache stays small where the row count does not.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuditEntry $entry the published ledger entry
   * @param OrganizationId $organizationId the organization being read
   *
   * @return bool true when the actor may be named to this audience
   */
  private function actorIsOrganizationMember(OrganizationAuditEntry $entry, OrganizationId $organizationId): bool
  {
    if ('user' !== $entry->actorType || null === $entry->actorId) {
      return false;
    }

    if (array_key_exists($entry->actorId, $this->cache)) {
      return $this->cache[$entry->actorId];
    }

    return $this->cache[$entry->actorId] = null !== $this->memberRepository->findByOrganizationAndUser(
      $organizationId,
      $entry->actorId,
    );
  }
  // #endregion
}
