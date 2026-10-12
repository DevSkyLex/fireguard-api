<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ComplianceHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 */
final readonly class ComplianceHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'createSafetyRegisterSnapshot',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot\CreateSafetyRegisterSnapshotCommand::class,
        resultClass: \Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot\CreateSafetyRegisterSnapshotResult::class,
        contractClasses: [\Compliance\Application\Contract\SafetyRegisterSnapshotView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'snapshot.id',
        resourceKind: 'compliance_snapshot',
      ),
      new HttpIdempotencyOperation(
        name: 'exportOrganizationSafetyRegister',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\ExportSafetyRegisterCommand::class,
        resultClass: \Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\ExportSafetyRegisterResult::class,
        statusCode: 200,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'exportFacilitySafetyRegister',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\ExportSafetyRegisterCommand::class,
        resultClass: \Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\ExportSafetyRegisterResult::class,
        statusCode: 200,
        requiredKey: true,
      ),
    ];
  }
  // #endregion
}
