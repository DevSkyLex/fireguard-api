<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Idempotency;

use Audit\Application\UseCase\Command\ExportAuditCsv\{ExportAuditCsvCommand, ExportAuditCsvResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares the effectful platform export's complete application result.
 *
 * @category Adapter
 */
final readonly class AuditHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('audit_events_export', 'auth', 'bus_owner', ExportAuditCsvCommand::class, ExportAuditCsvResult::class, requiredKey: true)];
  }
}
