<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Catalog;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Outbound\Internal\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort};
use Organization\Application\Port\Outbound\OrganizationQuotaContributionPort;
use Organization\Domain\ValueObject\OrganizationId;

/**
 * Class MemberQuotaContributionAdapter
 *
 * Counts active memberships plus pending invitations that reserve a slot.
 *
 * @category Adapter
 */
final readonly class MemberQuotaContributionAdapter implements OrganizationQuotaContributionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private OrganizationInvitationRepositoryPort $invitations,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationQuotaDefinition>
   */
  public static function definitions(): array
  {
    return [new OrganizationQuotaDefinition('members', 'Members', 'team members', 'Maximum number of organization members', 10)];
  }

  /**
   * Method usage
   *
   * @access public
   *
   * @return int occupied member slots
   */
  public function usage(string $organizationId, string $resourceKey): int
  {
    $id = OrganizationId::fromString($organizationId);

    return $this->members->countActiveByOrganizationId($id) + $this->invitations->countPendingByOrganizationId($id);
  }
  // #endregion
}
