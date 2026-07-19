<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Approval;

use Approval\Application\Port\Outbound\ApprovalMemberDirectoryPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationApprovalMemberDirectoryAdapter.
 *
 * Implements the Approval module's member directory port over the existing
 * Organization member/role read models. Fails closed on any lookup failure
 * (unknown organization, unknown member, unresolved user) so a directory
 * error can never bypass the four-eyes role check. The `admin` tier is
 * detected via the `organization.*` wildcard permission — the same signal
 * `OrganizationSystemRoleCatalog::ADMIN` grants — rather than a separate
 * role read model.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationApprovalMemberDirectoryAdapter implements ApprovalMemberDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  public function resolveMemberId(string $organizationId, string $userId): ?string
  {
    try {
      $orgId = OrganizationId::fromString($organizationId);
    } catch (InvalidValueException) {
      return null;
    }

    $member = $this->memberRepository->findByOrganizationAndUser($orgId, $userId);

    return null === $member ? null : (string) $member->id();
  }

  public function memberSatisfiesRole(string $organizationId, string $memberId, string $minRole): bool
  {
    if (OrganizationSystemRoleCatalog::MEMBER === $minRole) {
      // Reaching this point already implies membership (the caller resolved
      // $memberId via resolveMemberId()): the lowest tier is always satisfied.
      return true;
    }

    try {
      $orgId = OrganizationId::fromString($organizationId);
    } catch (InvalidValueException) {
      return false;
    }

    $userIds = $this->memberRepository->findUserIdsByMemberIds($orgId, [$memberId]);
    $userId = $userIds[$memberId] ?? null;

    if (null === $userId) {
      return false;
    }

    // The `admin` system role is the only one granted the `organization.*`
    // wildcard (see OrganizationSystemRoleCatalog::permissionsFor()); a
    // custom role explicitly granted the same wildcard is treated as
    // equivalent on purpose.
    return $this->authorization->hasPermission($userId, $organizationId, 'organization.*');
  }
  // #endregion
}
