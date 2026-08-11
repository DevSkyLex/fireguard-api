<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationRole;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationRoleResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationRoleResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationRoleResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the role identifier
   * @param string $organizationId the organization identifier
   * @param string $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is system-managed
   * @param DateTimeImmutable $createdAt the role creation datetime
   * @param string $description the role description
   * @param int $memberCount the number of ACTIVE members assigned to this role
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public array $permissions,
    public bool $isSystem,
    public DateTimeImmutable $createdAt,
    public string $description = '',
    public int $memberCount = 0,
  ) {
  }
  // #endregion
}
