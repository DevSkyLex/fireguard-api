<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteOrganizationRoleResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationRoleResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteOrganizationRoleResult class.
   *
   * @since 1.0.0
   *
   * @param string $roleId the deleted role identifier
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $roleId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
