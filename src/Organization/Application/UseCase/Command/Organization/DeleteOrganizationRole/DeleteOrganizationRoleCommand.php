<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteOrganizationRoleCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteOrganizationRoleCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier to delete
   */
  public function __construct(
    public string $organizationId,
    public string $roleId,
  ) {
  }
  // #endregion
}
