<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use LogicException;
use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Inbound\OrganizationSystemRoleCatalogPort;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

use function array_unique;
use function array_values;
use function sprintf;
use function usort;

/**
 * Class OrganizationSystemRoleRegistry
 *
 * Composes owner role grants while retaining persisted grants for history and user-managed roles.
 *
 * @category Service
 */
final readonly class OrganizationSystemRoleRegistry implements OrganizationSystemRoleCatalogPort
{
  // #region Properties
  /**
   * @var array<string, list<string>>
   */
  private array $grants;

  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<OrganizationSystemRoleContributionPort> $contributions owner grant manifests
   */
  public function __construct(iterable $contributions)
  {
    $definitions = [];
    foreach ($contributions as $contribution) {
      foreach ($contribution::definitions() as $definition) {
        if (isset($definitions[$definition->key])) {
          throw new LogicException(sprintf('Duplicate organization system-role contribution "%s".', $definition->key));
        }
        $definitions[$definition->key] = $definition;
      }
    }
    $ordered = array_values($definitions);
    usort($ordered, static fn (OrganizationSystemRoleDefinition $left, OrganizationSystemRoleDefinition $right): int => [$left->position, $left->key] <=> [$right->position, $right->key]);
    $grants = [];
    foreach ($ordered as $definition) {
      $grants[$definition->roleName] = array_values(array_unique([...($grants[$definition->roleName] ?? []), ...$definition->permissions]));
    }
    $this->grants = $grants;
  }

  // #endregion

  // #region Methods
  /**
   * Method permissionsFor
   *
   * @access public
   *
   * @return list<string>
   */
  public function permissionsFor(string $roleName): array
  {
    return $this->grants[$roleName] ?? [];
  }

  /**
   * Method mergePermissions
   *
   * @access public
   *
   * @param list<string> $permissions
   *
   * @return list<string>
   */
  public function mergePermissions(string $roleName, array $permissions, bool $isSystem): array
  {
    return $isSystem ? array_values(array_unique([...$permissions, ...$this->permissionsFor($roleName)])) : $permissions;
  }
  // #endregion
}
