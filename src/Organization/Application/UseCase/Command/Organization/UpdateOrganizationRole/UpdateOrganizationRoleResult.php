<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateOrganizationRoleResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationRoleResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateOrganizationRoleResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the role identifier
   * @param string $organizationId the organization identifier
   * @param string $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is system-managed
   * @param DateTimeImmutable $createdAt the role creation datetime
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public array $permissions,
    public bool $isSystem,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
