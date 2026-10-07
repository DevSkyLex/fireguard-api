<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport;

use DateTimeImmutable;
use MaintenanceExport\Application\Contract\{ExportOperation,ExportSourceState};
use MaintenanceExport\Application\Port\Outbound\{MaintenanceExportIdentityPort,MaintenanceExportRepositoryPort,MaintenanceExportSourcePort};
use MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\{ManageMaintenanceExportCommand,ManageMaintenanceExportHandler};
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\ExportDocument;
use MaintenanceExport\Domain\ValueObject\ExportArtifact;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort,EventDispatcherPort,UuidGeneratorPort};

use function array_values;
use function strtoupper;

/**
 * Class ManageMaintenanceExportHandlerTest
 * Exact replay authorization, append-only correction and no-event rejection are use-case guarantees.
 *
 * @category Test
 */
final class ManageMaintenanceExportHandlerTest extends TestCase
{
  private const string ORG = 'aa0e8400-e29b-41d4-a716-446655449001';

  private const string ACTOR = 'aa0e8400-e29b-41d4-a716-446655449002';

  private const string WORK = 'aa0e8400-e29b-41d4-a716-446655449003';

  private const string OP = 'aa0e8400-e29b-41d4-a716-446655449004';

  private const string DOC = 'aa0e8400-e29b-41d4-a716-446655449005';

  /**
   * @var array<string,ExportOperation> immutable receipts
   */
  private array $receipts = [];

  /**
   * @var array<string,ExportDocument> retained artifacts
   */
  private array $documents = [];

  /**
   * @var MaintenanceExportRepositoryPort&MockObject repository contract
   */
  private MaintenanceExportRepositoryPort $repository;

  /**
   * @var MaintenanceExportSourcePort&MockObject source capture contract
   */
  private MaintenanceExportSourcePort $sources;

  /**
   * @var EventDispatcherPort&MockObject atomic outbox contract
   */
  private EventDispatcherPort $events;

  protected function setUp(): void
  {
    $this->repository = $this->createMock(MaintenanceExportRepositoryPort::class);
    $expected = match ($this->name()) {
      'organizationScopeDenialPrecedesMalformedSensitiveSourceDetails' => 0,
      'missingFinancialReadCannotCapturePrivateFacts', 'correctionCreatesAnotherDocumentAndPreservesOriginalContext' => 1,
      default => 2,
    };
    $this->repository->expects(self::exactly($expected))->method('synchronized')->willReturnCallback(static fn (string $organizationId, callable $work): mixed => $work());
    $this->repository->method('operation')->willReturnCallback(fn (string $org, string $actor, string $operation): ?ExportOperation => $this->receipts[$operation] ?? null);
    $this->repository->method('saveOperation')->willReturnCallback(function (ExportOperation $operation): void { $this->receipts[$operation->clientOperationId] = $operation; });
    $this->repository->method('document')->willReturnCallback(fn (string $org, string $id): ?ExportDocument => $this->documents[$id] ?? null);
    $this->repository->method('saveDocument')->willReturnCallback(function (ExportDocument $document): void { $this->documents[$document->id] = clone $document; });
    $this->sources = $this->createMock(MaintenanceExportSourcePort::class);
    $this->events = $this->createMock(EventDispatcherPort::class);
  }

  #[Test]
  public function uppercaseReplayReturnsOriginalReceiptWithoutCapturingOrEnqueuingTwice(): void
  {
    $this->sources->expects(self::once())->method('capture')->with(self::ORG, [self::WORK], 'erp', false, false)->willReturn(new ExportSourceState($this->baseline(), null, null));
    $this->events->expects(self::once())->method('dispatch');
    $handler = $this->handler();
    $first = $handler($this->create());
    $bytes = $this->documents[self::DOC]->jsonBytes;
    $input = ['clientOperationId' => strtoupper(self::OP), 'interventionIds' => [strtoupper(self::WORK)], 'system' => 'erp', 'includeInternalCosts' => false];
    $second = $handler(new ManageMaintenanceExportCommand(strtoupper(self::ACTOR), strtoupper(self::ORG), 'create', null, 999, $input));
    self::assertTrue($second->replayed);
    self::assertSame($first->data, $second->data);
    self::assertSame($bytes, $this->documents[self::DOC]->jsonBytes);
    self::assertCount(1, $this->receipts);
  }

  #[Test]
  public function aReusedOperationForDifferentIntentConflictsWithoutAnotherEvent(): void
  {
    $this->sources->expects(self::once())->method('capture')->willReturn(new ExportSourceState($this->baseline(), null, null));
    $this->events->expects(self::once())->method('dispatch');
    $handler = $this->handler();
    $handler($this->create());
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('another declaration');
    $handler(new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'create', null, null, ['clientOperationId' => self::OP, 'interventionIds' => [self::WORK], 'system' => 'other']));
  }

  #[Test]
  public function missingFinancialReadCannotCapturePrivateFacts(): void
  {
    $this->sources->expects(self::never())->method('capture');
    $this->repository->expects(self::never())->method('saveDocument');
    $this->events->expects(self::never())->method('dispatch');
    $handler = $this->handler('organization.maintenance_cost.read');
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('permission');
    $handler($this->create(true));
  }

  #[Test]
  public function organizationScopeDenialPrecedesMalformedSensitiveSourceDetails(): void
  {
    $this->repository->expects(self::never())->method('synchronized');
    $this->sources->expects(self::never())->method('capture');
    $this->events->expects(self::never())->method('dispatch');
    $handler = $this->handler(null, OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('unavailable');
    $handler(new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'create', null, null, ['interventionIds' => ['secret-invalid-id']]));
  }

  #[Test]
  public function confirmationRequiresCurrentRevisionAndCannotRewriteArtifactBytes(): void
  {
    $this->sources->expects(self::never())->method('capture');
    $this->events->expects(self::once())->method('dispatch');
    $document = $this->document();
    $this->documents[self::DOC] = $document;
    $bytes = $document->jsonBytes;
    $handler = $this->handler();
    $result = $handler(new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'confirm', self::DOC, 1, ['clientOperationId' => self::OP, 'externalImportReference' => 'IMPORTED-88']));
    self::assertSame('import_confirmed', $result->data['state']);
    self::assertSame(2, $result->data['revision']);
    self::assertSame($bytes, $this->documents[self::DOC]->jsonBytes);
    $replay = $handler(new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'confirm', self::DOC, 0, ['clientOperationId' => self::OP, 'externalImportReference' => 'IMPORTED-88']));
    self::assertTrue($replay->replayed);
    self::assertSame($result->data, $replay->data);
  }

  #[Test]
  public function correctionCreatesAnotherDocumentAndPreservesOriginalContext(): void
  {
    $document = $this->document();
    $this->documents[self::DOC] = $document;
    $changed = $this->baseline();
    $changed['work']['minutes'] = 60;
    $this->sources->expects(self::once())->method('capture')->with(self::ORG, [self::WORK], 'erp', false, true)->willReturn(new ExportSourceState($changed, null, null));
    $this->events->expects(self::once())->method('dispatch');
    $id = 'aa0e8400-e29b-41d4-a716-446655449099';
    $result = $this->handler(id:$id)(new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'adjustment', self::DOC, 1, ['clientOperationId' => self::OP, 'reason' => 'Corrected time']));
    self::assertSame('adjustment', $result->data['kind']);
    self::assertSame(self::DOC, $result->data['adjustmentOf']);
    self::assertSame($document->jsonBytes, $this->documents[self::DOC]->jsonBytes);
    self::assertSame(-30, $this->documents[$id]->rows[0]['minutes']);
    self::assertSame(60, $this->documents[$id]->rows[1]['minutes']);
    self::assertSame($this->documents[$id]->rows[1]['id'], $this->documents[$id]->baseline['work']['id']);
  }

  /**
   * Method handler
   *
   * @return ManageMaintenanceExportHandler port-only use case
   */
  private function handler(?string $deniedPermission = null, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED, string $id = self::DOC): ManageMaintenanceExportHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturnCallback(static fn (string $actor, string $org, string $permission): OrganizationAccessDecision => $permission === $deniedPermission ? OrganizationAccessDecision::MISSING_PERMISSION : $decision);
    $identity = $this->createStub(MaintenanceExportIdentityPort::class);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn($id);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-07T12:00:00Z'));

    return new ManageMaintenanceExportHandler($this->repository, $this->sources, $identity, $authorization, $ids, $clock, $this->events);
  }

  /**
   * Method create
   *
   * @return ManageMaintenanceExportCommand stable initial intent
   */
  private function create(bool $financial = false): ManageMaintenanceExportCommand
  {
    return new ManageMaintenanceExportCommand(self::ACTOR, self::ORG, 'create', null, null, ['clientOperationId' => self::OP, 'interventionIds' => [self::WORK], 'system' => 'erp', 'includeInternalCosts' => $financial]);
  }

  /**
   * Method baseline
   *
   * @return array<string,array<string,mixed>> original validated row
   */
  private function baseline(): array
  {
    return ['work' => ['id' => 'source-row', 'kind' => 'prestation', 'logicalSourceKey' => 'work', 'sourceId' => self::WORK, 'interventionId' => self::WORK, 'minutes' => 30]];
  }

  /**
   * Method document
   *
   * @return ExportDocument fixture immutable artifact
   */
  private function document(): ExportDocument
  {
    $rows = [...array_values($this->baseline())];

    return new ExportDocument(self::DOC, self::ORG, self::ACTOR, 'initial', 'erp', false, [self::WORK], null, null, null, new DateTimeImmutable('2026-10-07T12:00:00Z'), $rows, $this->baseline(), ExportArtifact::json(['rows' => $rows]), ExportArtifact::csv($rows, false));
  }
}
