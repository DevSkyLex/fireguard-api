<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\CreatePermission;

use Authorization\Application\Port\Outbound\Internal\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

/**
 * Handler CreatePermissionHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatePermissionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   * @param UuidGeneratorPort $uuidFactory the UUID factory
   */
  public function __construct(
    private PermissionRepositoryPort $permissionRepository,
    private UuidGeneratorPort $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the CreatePermissionCommand.
   *
   * @since 1.0.0
   *
   * @param CreatePermissionCommand $command the command
   *
   * @return CreatePermissionResult the result
   */
  public function __invoke(CreatePermissionCommand $command): CreatePermissionResult
  {
    $permission = Permission::create(
      id: new PermissionId($this->uuidFactory->generate()),
      name: new PermissionName(value: $command->name),
      description: $command->description ?? '',
    );

    $this->permissionRepository->save(permission: $permission);

    return new CreatePermissionResult(
      id: $permission->id()->value,
      name: $permission->name()->value,
      description: $permission->description(),
      createdAt: $permission->createdAt()->format('Y-m-d H:i:s'),
    );
  }
  // #endregion
}
