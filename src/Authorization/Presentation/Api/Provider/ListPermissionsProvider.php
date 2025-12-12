<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Presentation\Api\Dto\PermissionOutput;

use function array_map;

/**
 * Provider ListPermissionsProvider
 * @final
 *
 * API Platform provider for listing all permissions.
 *
 * @category Provider
 * @package Authorization\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProviderInterface<PermissionOutput>
 */
final readonly class ListPermissionsProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * ListPermissionsProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionRepositoryPort $permissionRepository The permission repository.
   */
  public function __construct(
    private readonly PermissionRepositoryPort $permissionRepository
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the list of permissions.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return array<PermissionOutput> The list of permissions.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $permissions = $this->permissionRepository->findAll();

    return array_map(
      fn(Permission $permission) => $this->mapPermissionToOutput($permission),
      $permissions
    );
  }

  /**
   * Method mapPermissionToOutput
   *
   * Maps a Permission to PermissionOutput.
   *
   * @access private
   * @since 1.0.0
   *
   * @param Permission $permission The permission.
   *
   * @return PermissionOutput The permission output.
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
  //#endregion
}
