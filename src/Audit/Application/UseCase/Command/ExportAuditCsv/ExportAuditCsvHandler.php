<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Command\ExportAuditCsv;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView, AuditExportAccessDeniedException, AuditExportTooLargeException};
use Audit\Application\Contract\Event\AuditEventsExportedEvent;
use Audit\Application\Port\Outbound\Export\AuditCsvEncodingPort;
use Audit\Application\Port\Outbound\Internal\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\ExportAuditEvents\ExportAuditEventsHandler;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use DateTimeImmutable;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function base64_encode;
use function sprintf;

/**
 * Commits the fully materialized CSV and its durable export fact in the auth transaction.
 *
 * @category Handler
 */
final readonly class ExportAuditCsvHandler implements CommandHandler
{
  /**
   * @param AuditEventRepositoryPort $repository owning auth ledger projections
   * @param AuthorizationPort $authorization fresh platform export permission
   * @param AuditCsvEncodingPort $encoding bounded original CSV encoder
   * @param EventDispatcherPort $eventDispatcher strict auth transactional outbox
   * @param TransactionManagerPort $transaction explicitly wired auth transaction
   */
  public function __construct(private AuditEventRepositoryPort $repository, private AuthorizationPort $authorization, private AuditCsvEncodingPort $encoding, private EventDispatcherPort $eventDispatcher, private TransactionManagerPort $transaction)
  {
  }

  /**
   * @param ExportAuditCsvCommand $command current authenticated export request
   *
   * @return ExportAuditCsvResult original bytes and filename
   */
  public function __invoke(ExportAuditCsvCommand $command): ExportAuditCsvResult
  {
    return $this->transaction->transactional(function () use ($command): ExportAuditCsvResult {
      if ('' === $command->actorUserId || !$this->authorization->hasPermission($command->actorUserId, 'audit.export')) {
        throw new AuditExportAccessDeniedException();
      }
      $total = $this->repository->countMatching($command->criteria);
      if ($total > ExportAuditEventsHandler::MAX_EXPORT_ROWS) {
        throw AuditExportTooLargeException::exceedsCap($total, ExportAuditEventsHandler::MAX_EXPORT_ROWS);
      }
      $exported = 0;
      $csv = $this->encoding->encode($this->boundedRows($this->repository->stream($command->criteria), $exported));
      $result = new ExportAuditCsvResult(base64_encode($csv), sprintf('audit-events-export-%s.csv', new DateTimeImmutable()->format('Ymd-His')), $exported);
      $this->eventDispatcher->dispatch(new AuditEventsExportedEvent($command->actorUserId, $command->criteria->tenantId, 'csv', $exported, $this->filterKeys($command->criteria)));

      return $result;
    });
  }

  /**
   * Enforces the row limit including ledger appends committed after the initial count.
   *
   * @param iterable<AuditEventView> $rows ordered current ledger projection
   * @param int $exported actual materialized row count
   *
   * @return iterable<AuditEventView> bounded original rows
   */
  private function boundedRows(iterable $rows, int &$exported): iterable
  {
    foreach ($rows as $view) {
      if (++$exported > ExportAuditEventsHandler::MAX_EXPORT_ROWS) {
        throw AuditExportTooLargeException::exceedsCap($exported, ExportAuditEventsHandler::MAX_EXPORT_ROWS);
      }

      yield $view;
    }
  }

  /**
   * @param AuditEventSearchCriteria $criteria original trusted filters
   *
   * @return list<string> applied names without free-form values
   */
  private function filterKeys(AuditEventSearchCriteria $criteria): array
  {
    $keys = [];
    foreach (['action', 'actorType', 'actorId', 'subjectType', 'subjectId', 'clientId', 'tenantId', 'ipHash', 'from', 'to'] as $key) {
      if (null !== $criteria->{$key}) {
        $keys[] = $key;
      }
    }

    return $keys;
  }
}
