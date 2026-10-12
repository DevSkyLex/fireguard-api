<?php

declare(strict_types=1);

namespace Import\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ImportHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class ImportHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'confirm_import_simulation',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Import\Application\UseCase\Command\ConfirmImportSimulation\ConfirmImportSimulationCommand::class,
        resultClass: \Import\Application\UseCase\Query\GetImportJob\GetImportJobResult::class,
        statusCode: 202,
        requiredKey: true,
        resultIdentifierProperty: 'importJobId',
        resourceKind: 'import_job',
      ),
      new HttpIdempotencyOperation(
        name: 'resume_import_job',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Import\Application\UseCase\Command\ResumeImportJob\ResumeImportJobCommand::class,
        resultClass: \Import\Application\UseCase\Query\GetImportJob\GetImportJobResult::class,
        statusCode: 202,
        requiredKey: true,
        resultIdentifierProperty: 'importJobId',
        resourceKind: 'import_job',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/imports_post',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Import\Application\UseCase\Command\CreateImportJob\CreateImportJobCommand::class,
        resultClass: \Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult::class,
        statusCode: 202,
        requiredKey: true,
        resultIdentifierProperty: 'importJobId',
        resourceKind: 'import_job',
      ),
    ];
  }
  // #endregion
}
