<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationJoin;

use Organization\Domain\Exception\OrganizationJoinInputException;
use Organization\Domain\ValueObject\OrganizationJoinMode;

/**
 * Domain OrganizationAccessPolicy.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAccessPolicy
{
  /**
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param OrganizationJoinMode $mode the configured mode
   * @param ?string $roleId the immediate-join role
   */
  public function __construct(public string $organizationId, public OrganizationJoinMode $mode = OrganizationJoinMode::INVITATION_ONLY, public ?string $roleId = null)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinMode $mode the requested mode
   * @param ?string $roleId the selected role
   */
  public function configure(OrganizationJoinMode $mode, ?string $roleId): void
  {
    if (OrganizationJoinMode::AUTOMATIC === $mode && null === $roleId) {
      throw new OrganizationJoinInputException('organization_join_role_required');
    }
    $this->mode = $mode;
    $this->roleId = $roleId;
  }
}
