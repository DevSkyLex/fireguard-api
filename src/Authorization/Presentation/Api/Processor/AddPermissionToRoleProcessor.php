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
use function is_object;
use function is_string;
use function property_exists;

/**
 * Processor AddPermissionToRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, RoleOutput>
 */
final readonly class AddPermissionToRoleProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AddPermissionToRoleProcessor class.
   *
   * @since 1.0.0
   *
   * @param RoleRepositoryPort       $roleRepository       the role repository
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   */
  public function __construct(
    private readonly RoleRepositoryPort $roleRepository,
    private readonly PermissionRepositoryPort $permissionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes adding a permission to a role.
   *
   * @since 1.0.0
   *
   * @param mixed                $data         the input data
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return RoleOutput the updated role
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
  {
    $roleId = $uriVariables['roleId'] ?? null;
    $permissionId = is_object($data) && property_exists($data, 'permissionId') ? $data->permissionId : null;

    if (!is_string($roleId)) {
      throw RoleNotFoundException::withId(roleId: 'unknown');
    }

    if (!is_string($permissionId)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $role = $this->roleRepository->findById(id: new RoleId(value: $roleId));

    if (null === $role) {
      throw RoleNotFoundException::withId(roleId: $roleId);
    }

    $permission = $this->permissionRepository->findById(id: new PermissionId(value: $permissionId));

    if (null === $permission) {
      throw PermissionNotFoundException::withId(permissionId: $permissionId);
    }

    // Add permission to role (void return)
    $role->addPermission(permission: $permission);

    // Save
    $this->roleRepository->save(role: $role);

    // Return output
    return $this->mapRoleToOutput(role: $role);
  }

  /**
   * Method mapRoleToOutput.
   *
   * Maps a Role to RoleOutput.
   *
   * @since 1.0.0
   *
   * @param Role $role the role
   *
   * @return RoleOutput the role output
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
      fn (Permission $permission) => $this->mapPermissionToOutput($permission),
      $role->permissions()
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
