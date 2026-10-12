<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class AuthorizationHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 */
final readonly class AuthorizationHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'permission_create',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Permission\CreatePermission\CreatePermissionCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Permission\CreatePermission\CreatePermissionResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'permission',
      ),
      new HttpIdempotencyOperation(
        name: 'permission_update',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Permission\UpdatePermission\UpdatePermissionCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Permission\UpdatePermission\UpdatePermissionResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'permission',
      ),
      new HttpIdempotencyOperation(
        name: 'permission_delete',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'permission',
      ),
      new HttpIdempotencyOperation(
        name: 'role_create',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Role\CreateRole\CreateRoleCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Role\CreateRole\CreateRoleResult::class,
        contractClasses: [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'role',
      ),
      new HttpIdempotencyOperation(
        name: 'role_update',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Role\UpdateRole\UpdateRoleCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Role\UpdateRole\UpdateRoleResult::class,
        contractClasses: [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'role',
      ),
      new HttpIdempotencyOperation(
        name: 'role_delete',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'role',
      ),
      new HttpIdempotencyOperation(
        name: 'role_add_permission',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Role\AddPermissionToRole\AddPermissionToRoleCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Role\AddPermissionToRole\AddPermissionToRoleResult::class,
        contractClasses: [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'role',
      ),
      new HttpIdempotencyOperation(
        name: 'role_remove_permission',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\RemovePermissionFromRoleCommand::class,
        resultClass: \Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\RemovePermissionFromRoleResult::class,
        contractClasses: [\Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'role',
      ),
    ];
  }
  // #endregion
}
