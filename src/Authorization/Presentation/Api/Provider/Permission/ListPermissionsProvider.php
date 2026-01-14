<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Permission\ListPermissions\{ListPermissionsQuery, ListPermissionsResult};
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

use function array_map;
use function assert;

/**
 * Provider ListPermissionsProvider.
 *
 * Provides the list of permissions.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PermissionOutput>
 */
final readonly class ListPermissionsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListPermissionsProvider class.
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
   * Provides the list of permissions.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return array<PermissionOutput> the list of permissions
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $query = new ListPermissionsQuery();
    $result = $this->queryBus->ask(query: $query);

    assert($result instanceof ListPermissionsResult);

    return array_map(
      fn (GetPermissionResult $permission) => $this->mapPermissionToOutput($permission),
      $result->permissions,
    );
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
