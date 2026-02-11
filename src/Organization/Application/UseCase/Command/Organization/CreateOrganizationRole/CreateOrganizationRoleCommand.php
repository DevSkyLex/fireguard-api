<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganizationRole;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateOrganizationRoleCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateOrganizationRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateOrganizationRoleCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is system-managed
   */
  public function __construct(
    public string $organizationId,
    public string $name,
    public array $permissions,
    public bool $isSystem = false,
  ) {
  }
  // #endregion
}
