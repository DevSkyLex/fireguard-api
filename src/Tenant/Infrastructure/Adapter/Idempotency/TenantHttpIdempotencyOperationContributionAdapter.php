<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class TenantHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 */
final readonly class TenantHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'tenant_create',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\CreateTenant\CreateTenantCommand::class,
        resultClass: \Tenant\Application\UseCase\Command\Tenant\CreateTenant\CreateTenantResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'tenantId',
        resourceKind: 'tenant',
      ),
      new HttpIdempotencyOperation(
        name: 'tenant_update',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantCommand::class,
        resultClass: \Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'tenantId',
        resourceKind: 'tenant',
        queryProjections: [
          new HttpIdempotencyQueryProjection(
            queryClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery::class,
            resultClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult::class,
            contractClasses: [\Tenant\Domain\ValueObject\TenantSettings::class],
          ),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'tenant_replace',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantCommand::class,
        resultClass: \Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'tenantId',
        resourceKind: 'tenant',
        queryProjections: [
          new HttpIdempotencyQueryProjection(
            queryClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery::class,
            resultClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult::class,
            contractClasses: [\Tenant\Domain\ValueObject\TenantSettings::class],
          ),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'tenant_delete',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantCommand::class,
        resultClass: \Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'tenantId',
        resourceKind: 'tenant',
      ),
      new HttpIdempotencyOperation(
        name: 'tenant_activate',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\ActivateTenant\ActivateTenantCommand::class,
        resultClass: \Shared\Application\Message\VoidResult::class,
        statusCode: 200,
        requiredKey: true,
        queryProjections: [
          new HttpIdempotencyQueryProjection(
            queryClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery::class,
            resultClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult::class,
            contractClasses: [\Tenant\Domain\ValueObject\TenantSettings::class],
          ),
        ],
      ),
      new HttpIdempotencyOperation(
        name: 'tenant_deactivate',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: \Tenant\Application\UseCase\Command\Tenant\DeactivateTenant\DeactivateTenantCommand::class,
        resultClass: \Shared\Application\Message\VoidResult::class,
        statusCode: 200,
        requiredKey: true,
        queryProjections: [
          new HttpIdempotencyQueryProjection(
            queryClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery::class,
            resultClass: \Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult::class,
            contractClasses: [\Tenant\Domain\ValueObject\TenantSettings::class],
          ),
        ],
      ),
    ];
  }
  // #endregion
}
