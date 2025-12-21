<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Presentation\Api\Dto\PermissionOutput;

use function array_map;
use function count;

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
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   */
  public function __construct(
    private readonly PermissionRepositoryPort $permissionRepository,
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
   * @return ArrayPaginator<int, PermissionOutput> the paginated list of permissions
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $permissions = $this->permissionRepository->findAll();

    $output = array_map(
      fn (Permission $permission): PermissionOutput => $this->mapPermissionToOutput($permission),
      $permissions,
    );

    return new ArrayPaginator(
      results: $output,
      firstResult: 0,
      maxResults: count($output),
    );
  }

  /**
   * Method mapPermissionToOutput.
   *
   * Maps a Permission to PermissionOutput.
   *
   * @since 1.0.0
   *
   * @param Permission $permission the permission
   *
   * @return PermissionOutput the permission output
   */
  private function mapPermissionToOutput(Permission $permission): PermissionOutput
  {
    $output = new PermissionOutput();
    $output->id = $permission->id()->value;
    $output->name = $permission->name()->value;
    $output->description = $permission->description();
    $output->createdAt = $permission->createdAt()->format('Y-m-d H:i:s');

    return $output;
  }
  // #endregion
}
