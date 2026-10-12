<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Command\ExportAuditCsv;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView, AuditExportAccessDeniedException, AuditExportSizeLimitException, AuditExportTooLargeException};
use Audit\Application\Contract\Event\AuditEventsExportedEvent;
use Audit\Application\Port\Outbound\Export\AuditCsvEncodingPort;
use Audit\Application\Port\Outbound\Internal\AuditEventRepositoryPort;
use Audit\Application\UseCase\Command\ExportAuditCsv\{ExportAuditCsvCommand, ExportAuditCsvHandler};
use Authorization\Application\Port\Inbound\AuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function base64_decode;
use function iterator_to_array;

/**
 * Proves original bytes are ready before the durable export fact is written.
 *
 * @category Test
 */
final class ExportAuditCsvHandlerTest extends TestCase
{
  public function testCapturesOriginalCsvAndOnlyFilterNamesBeforeDispatch(): void
  {
    $criteria = new AuditEventSearchCriteria(actorId: 'private-filter-value');
    $repository = $this->createStub(AuditEventRepositoryPort::class);
    $repository->method('countMatching')->willReturn(1);
    $repository->method('stream')->willReturn([$this->row()]);
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $encoding = $this->createMock(AuditCsvEncodingPort::class);
    $encoded = false;
    $encoding->expects(self::once())->method('encode')->willReturnCallback(static function (iterable $rows) use (&$encoded): string {
      iterator_to_array($rows);
      $encoded = true;

      return "original,csv\n";
    });
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static function (AuditEventsExportedEvent $event) use (&$encoded): bool { return $encoded && ['actorId'] === $event->filterKeys && 1 === $event->rowCount; }));
    $result = new ExportAuditCsvHandler($repository, $authorization, $encoding, $events, $this->transaction())(new ExportAuditCsvCommand($criteria, 'auditor'));
    self::assertSame("original,csv\n", base64_decode($result->csvBase64, true));
    self::assertStringStartsWith('audit-events-export-', $result->fileName);
    self::assertSame(1, $result->total);
  }

  public function testByteLimitFailureEmitsNoExportFact(): void
  {
    $repository = $this->createStub(AuditEventRepositoryPort::class);
    $repository->method('countMatching')->willReturn(1);
    $repository->method('stream')->willReturn([]);
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $encoding = $this->createMock(AuditCsvEncodingPort::class);
    $encoding->expects(self::once())->method('encode')->willThrowException(new AuditExportSizeLimitException());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(AuditExportSizeLimitException::class);
    new ExportAuditCsvHandler($repository, $authorization, $encoding, $events, $this->transaction())(new ExportAuditCsvCommand(new AuditEventSearchCriteria(), 'auditor'));
  }

  public function testDeniedCallerDoesNotReadTheLedgerOrEmitAnExportFact(): void
  {
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::never())->method('countMatching');
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(AuditExportAccessDeniedException::class);
    new ExportAuditCsvHandler($repository, $authorization, $this->createStub(AuditCsvEncodingPort::class), $events, $this->transaction())(new ExportAuditCsvCommand(new AuditEventSearchCriteria(), 'auditor'));
  }

  public function testConcurrentRowsBeyondTheInitialCountCannotExceedTheRowLimit(): void
  {
    $repository = $this->createStub(AuditEventRepositoryPort::class);
    $repository->method('countMatching')->willReturn(50_000);
    $view = $this->row();
    $repository->method('stream')->willReturn((static function () use ($view) {
      for ($index = 0; $index < 50_001; ++$index) {
        yield $view;
      }
    })());
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $encoding = $this->createStub(AuditCsvEncodingPort::class);
    $encoding->method('encode')->willReturnCallback(static function (iterable $rows): string {
      iterator_to_array($rows);

      return '';
    });
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(AuditExportTooLargeException::class);
    new ExportAuditCsvHandler($repository, $authorization, $encoding, $events, $this->transaction())(new ExportAuditCsvCommand(new AuditEventSearchCriteria(), 'auditor'));
  }

  /**
   * @return TransactionManagerPort immediate test transaction boundary
   */
  private function transaction(): TransactionManagerPort
  {
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transaction;
  }

  /**
   * @return AuditEventView immutable representative row
   */
  private function row(): AuditEventView
  {
    return new AuditEventView('audit-id', 'audit.example', 'user', 'user-id', null, null, null, null, null, null, null, null, null, [], '2026-10-12T10:00:00Z', '2026-10-12T10:00:00Z', 'global', 1, null, 'event-hash');
  }
}
