<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateOrganizationRoleCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationRoleCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateOrganizationRoleCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier
   * @param list<string> $permissions the role permissions
   * @param string|null $description the role description
   */
  public function __construct(
    public string $organizationId,
    public string $roleId,
    public array $permissions,
    public ?string $description = null,
  ) {
  }
  // #endregion
}
