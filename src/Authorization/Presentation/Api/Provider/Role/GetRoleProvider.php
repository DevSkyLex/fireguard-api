<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider\Role;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Role\GetRole\{GetRoleQuery, GetRoleResult};
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

use function array_map;
use function assert;
use function is_string;

/**
 * Provider GetRoleProvider.
 *
 * Provides the role output.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<RoleOutput>
 */
final readonly class GetRoleProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetRoleProvider class.
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
   * Provides the role output.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return RoleOutput|null the role output or null if not found
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?RoleOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      return null;
    }

    $query = new GetRoleQuery(roleId: $id);

    try {
      $result = $this->queryBus->ask(query: $query);
    } catch (RoleNotFoundException) {
      return null;
    }

    assert($result instanceof GetRoleResult);

    $output = new RoleOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->description = $result->description;
    $output->isSystem = $result->isSystem;
    $output->createdAt = $result->createdAt;
    $output->permissions = array_map(
      fn (GetPermissionResult $permission) => $this->mapPermissionToOutput($permission),
      $result->permissions,
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
