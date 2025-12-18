<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Presentation\Api\Dto\PermissionOutput;
use Authorization\Presentation\Api\Dto\RoleOutput;

use function array_map;

/**
 * Processor RemovePermissionFromRoleProcessor
 * @final
 *
 * API Platform processor for removing a permission from a role.
 *
 * @category Processor
 * @package Authorization\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, RoleOutput>
 */
final readonly class RemovePermissionFromRoleProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RemovePermissionFromRoleProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository The role repository.
   * @param PermissionRepositoryPort $permissionRepository The permission repository.
   */
  public function __construct(
    private readonly RoleRepositoryPort $roleRepository,
    private readonly PermissionRepositoryPort $permissionRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes removing a permission from a role.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return RoleOutput The updated role.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
  {
    $roleId = $uriVariables['roleId'] ?? null;
    $permissionId = $uriVariables['permissionId'] ?? null;

    if (!is_string($roleId)) {
      throw RoleNotFoundException::withId(roleId: 'unknown');
    }

    if (!is_string($permissionId)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $role = $this->roleRepository->findById(id: new RoleId(value: $roleId));

    if ($role === null) {
      throw RoleNotFoundException::withId(roleId: $roleId);
    }

    $permission = $this->permissionRepository->findById(id: new PermissionId(value: $permissionId));

    if ($permission === null) {
      throw PermissionNotFoundException::withId(permissionId: $permissionId);
    }

    // Remove permission from role (void return)
    $role->removePermission(permission: $permission);

    // Save
    $this->roleRepository->save(role: $role);

    // Return output
    return $this->mapRoleToOutput(role: $role);
  }

  /**
   * Method mapRoleToOutput
   *
   * Maps a Role to RoleOutput.
   *
   * @access private
   * @since 1.0.0
   *
   * @param Role $role The role.
   *
   * @return RoleOutput The role output.
   */
  private function mapRoleToOutput(Role $role): RoleOutput
  {
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
