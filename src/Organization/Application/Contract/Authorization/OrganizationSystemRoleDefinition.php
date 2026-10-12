<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Authorization;

/**
 * Class OrganizationSystemRoleDefinition
 *
 * Publishes a uniquely owned grant contribution to a system role; positions preserve existing grant order.
 *
 * @category Contract
 */
final readonly class OrganizationSystemRoleDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $key unique owner contribution identifier
   * @param string $roleName stable role identifier
   * @param list<string> $permissions canonical grants added by the owner
   * @param int $position stable grant order
   */
  public function __construct(public string $key, public string $roleName, public array $permissions, public int $position = 0)
  {
  }
  // #endregion
}
