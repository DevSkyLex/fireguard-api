<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor\Role;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\UseCase\Command\Role\UpdateRole\{UpdateRoleCommand, UpdateRoleResult};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Presentation\Api\Dto\Input\Role\RoleInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Shared\Application\Port\Inbound\CommandBusPort;

use function array_map;
use function array_values;
use function is_string;

/**
 * Processor UpdateRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RoleInput, RoleOutput>
 */
final readonly class UpdateRoleProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateRoleProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the role update.
   *
   * @since 1.0.0
   *
   * @param RoleInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return RoleOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
  {
    /** @var RoleInput $data */
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw RoleNotFoundException::withId(roleId: 'unknown');
    }

    $command = new UpdateRoleCommand(
      roleId: $id,
      name: $data->name,
      description: $data->description,
      permissionIds: array_values($data->permissionIds),
    );

    /** @var UpdateRoleResult $result */
    $result = $this->commandBus->dispatch(command: $command);

    return $this->mapRoleToOutput($result);
  }

  /**
   * Method mapRoleToOutput.
   *
   * Maps a Role to RoleOutput.
   *
   * @since 1.0.0
   *
   * @param UpdateRoleResult $result the result
   *
   * @return RoleOutput the role output
   */
  private function mapRoleToOutput(UpdateRoleResult $result): RoleOutput
  {
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
