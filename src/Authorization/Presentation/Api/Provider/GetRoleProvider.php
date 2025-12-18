<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Presentation\Api\Dto\PermissionOutput;
use Authorization\Presentation\Api\Dto\RoleOutput;

/**
 * Provider GetRoleProvider
 * @final
 *
 * API Platform provider for fetching a single role.
 *
 * @category Provider
 * @package Authorization\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProviderInterface<RoleOutput>
 */
final readonly class GetRoleProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GetRoleProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository The role repository.
   */
  public function __construct(
    private readonly RoleRepositoryPort $roleRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the role output.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return RoleOutput|null The role output or null if not found.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?RoleOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      return null;
    }

    $role = $this->roleRepository->findById(id: new RoleId(value: $id));

    if ($role === null) {
      return null;
    }

    $output = new RoleOutput();
    $output->id = $role->id()->value;
    $output->name = $role->name()->value;
    $output->description = $role->description();
    $output->isSystem = $role->isSystem();
    $output->createdAt = $role->createdAt()->format('Y-m-d H:i:s');
    $output->permissions = array_map(
      fn(Permission $permission) => $this->mapPermissionToOutput($permission),
      $role->permissions()
    );

    return $output;
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
