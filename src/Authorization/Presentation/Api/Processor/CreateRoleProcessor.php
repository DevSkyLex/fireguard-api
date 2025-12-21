<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Authorization\Presentation\Api\Dto\PermissionOutput;
use Authorization\Presentation\Api\Dto\RoleInput;
use Authorization\Presentation\Api\Dto\RoleOutput;
use Symfony\Component\Uid\Uuid;

use function array_map;

/**
 * Processor CreateRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RoleInput, RoleOutput>
 */
final readonly class CreateRoleProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreateRoleProcessor class.
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
   * Processes the role creation.
   *
   * @since 1.0.0
   *
   * @param RoleInput            $data         the input data
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return RoleOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
  {
    /** @var RoleInput $data */

    // Collect permissions
    $permissions = [];
    foreach ($data->permissionIds as $permissionId) {
      $permission = $this->permissionRepository->findById(id: new PermissionId(value: $permissionId));
      if (null !== $permission) {
        $permissions[] = $permission;
      }
    }

    // Create the role
    $role = Role::create(
      id: new RoleId(value: Uuid::v7()->toRfc4122()),
      name: new RoleName(value: $data->name),
      description: $data->description ?? '',
      isSystem: $data->isSystem,
      tenantId: null
    );

    // Add permissions
    foreach ($permissions as $permission) {
      $role->addPermission(permission: $permission);
    }

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
