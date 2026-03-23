<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider\Role;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Role\GetRole\GetRoleResult;
use Authorization\Application\UseCase\Query\Role\ListRoles\{ListRolesQuery, ListRolesResult};
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

use function array_map;
use function assert;
use function filter_var;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Provider ListRolesProvider.
 *
 * Provides the list of roles.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<RoleOutput>
 */
final readonly class ListRolesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListRolesProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the list of roles.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return array<RoleOutput> the list of roles
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $isSystem = null;
    if (isset($filters['isSystem'])) {
      $isSystem = filter_var($filters['isSystem'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    $query = new ListRolesQuery(isSystem: $isSystem);
    $result = $this->queryBus->ask(query: $query);

    assert($result instanceof ListRolesResult);

    return array_map(
      fn (GetRoleResult $role) => $this->mapRoleToOutput($role),
      $result->roles,
    );
  }

  /**
   * Method mapRoleToOutput.
   *
   * Maps a Role to RoleOutput.
   *
   * @since 1.0.0
   *
   * @param GetRoleResult $role the role
   *
   * @return RoleOutput the role output
   */
  private function mapRoleToOutput(GetRoleResult $role): RoleOutput
  {
    $output = new RoleOutput();
    $output->id = $role->id;
    $output->name = $role->name;
    $output->description = $role->description;
    $output->isSystem = $role->isSystem;
    $output->createdAt = $role->createdAt;
    $output->permissions = array_map(
      fn (GetPermissionResult $permission) => $this->mapPermissionToOutput($permission),
      $role->permissions,
    );

    return $output;
  }

  /**
   * Method mapPermissionToOutput.
   *
   * Maps a Permission to PermissionOutput.
   *
   * @since 1.0.0
   *
   * @param GetPermissionResult $permission the permission
   *
   * @return PermissionOutput the permission output
   */
  private function mapPermissionToOutput(GetPermissionResult $permission): PermissionOutput
  {
    $output = new PermissionOutput();
    $output->id = $permission->id;
    $output->name = $permission->name;
    $output->description = $permission->description;
    $output->createdAt = $permission->createdAt;

    return $output;
  }
  // #endregion
}
