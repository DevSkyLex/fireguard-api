<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Command\Permission\CreatePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;

/**
 * Handler CreatePermissionHandler.
 *
 * @category Handler
 *
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
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private PermissionRepositoryPort $permissionRepository,
    private UuidFactory $uuidFactory,
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
      id: $this->uuidFactory->create(PermissionId::class),
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
