<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory;

use DateTimeImmutable;
use Equipment\Application\Contract\Intervention\{InterventionServiceReport, ServicedEquipmentEntry};
use Equipment\Application\Port\Outbound\{InterventionServiceReportPort, MaintenanceLogRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory\{RecordInterventionServiceHistoryCommand, RecordInterventionServiceHistoryHandler};
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{MaintenanceLogId, MaintenanceLogSource};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\VoidResult;

use function hash;

#[CoversClass(RecordInterventionServiceHistoryHandler::class)]
final class RecordInterventionServiceHistoryHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655482001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655482002';

  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655482003';

  private const string EQUIPMENT_ID_1 = '550e8400-e29b-41d4-a716-446655482004';

  private const string EQUIPMENT_ID_2 = '550e8400-e29b-41d4-a716-446655482005';

  private const string ACTOR_ID = '550e8400-e29b-41d4-a716-446655482006';

  private const string CHANGE_TOKEN_1 = '550e8400-e29b-41d4-a716-446655482007';

  private const string CHANGE_TOKEN_2 = '550e8400-e29b-41d4-a716-446655482008';

  #[Test]
  public function testInvokeReturnsVoidResultWhenNoServiceReportIsFound(): void
  {
    $serviceReport = $this->createMock(InterventionServiceReportPort::class);
    $serviceReport->expects(self::once())
      ->method('serviceReport')
      ->with(self::INTERVENTION_ID)
      ->willReturn(null);

    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::never())->method('appendInterventionServiceEntry');

    $handler = $this->handler($serviceReport, $maintenanceLogRepository);

    $result = $handler->__invoke($this->command());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function testInvokeAppendsOneEntryPerServicedEquipment(): void
  {
    $report = new InterventionServiceReport(
      number: 42,
      actorId: self::ACTOR_ID,
      equipment: [
        new ServicedEquipmentEntry(
          equipmentId: self::EQUIPMENT_ID_1,
          action: 'status_change',
          changeToken: self::CHANGE_TOKEN_1,
          workItemId: null,
        ),
        new ServicedEquipmentEntry(
          equipmentId: self::EQUIPMENT_ID_2,
          action: 'update',
          changeToken: self::CHANGE_TOKEN_2,
          workItemId: '550e8400-e29b-41d4-a716-446655482009',
        ),
      ],
    );

    $serviceReport = $this->createStub(InterventionServiceReportPort::class);
    $serviceReport->method('serviceReport')->willReturn($report);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $seenDedupKeys = [];
    $maintenanceLogRepository->expects(self::exactly(2))
      ->method('appendInterventionServiceEntry')
      ->willReturnCallback(function (EquipmentMaintenanceLog $log, string $dedupKey) use (&$seenDedupKeys): void {
        $seenDedupKeys[] = $dedupKey;

        self::assertSame(MaintenanceLogSource::INTERVENTION, $log->source());
        self::assertSame(self::INTERVENTION_ID, $log->interventionId());
        self::assertSame(42, $log->interventionNumber());
        self::assertSame(self::ACTOR_ID, $log->actorId());
        self::assertSame($log->startedAt(), $log->completedAt());
      });

    $handler = $this->handler($serviceReport, $maintenanceLogRepository);

    $handler->__invoke($this->command());

    self::assertSame([
      hash('sha1', 'intervention_change:' . self::CHANGE_TOKEN_1),
      hash('sha1', 'intervention_change:' . self::CHANGE_TOKEN_2),
    ], $seenDedupKeys);
  }

  #[Test]
  public function testInvokeToleratesANullActorId(): void
  {
    $report = new InterventionServiceReport(
      number: 7,
      actorId: null,
      equipment: [
        new ServicedEquipmentEntry(
          equipmentId: self::EQUIPMENT_ID_1,
          action: 'update',
          changeToken: self::CHANGE_TOKEN_1,
          workItemId: null,
        ),
      ],
    );

    $serviceReport = $this->createStub(InterventionServiceReportPort::class);
    $serviceReport->method('serviceReport')->willReturn($report);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())
      ->method('appendInterventionServiceEntry')
      ->willReturnCallback(function (EquipmentMaintenanceLog $log): void {
        self::assertNull($log->actorId());
      });

    $handler = $this->handler($serviceReport, $maintenanceLogRepository);

    $handler->__invoke($this->command());
  }

  private function handler(
    InterventionServiceReportPort $serviceReport,
    MaintenanceLogRepositoryPort $maintenanceLogRepository,
  ): RecordInterventionServiceHistoryHandler {
    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('create')
      ->with(MaintenanceLogId::class)
      ->willReturnCallback(static fn (): MaintenanceLogId => MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655482099'));

    return new RecordInterventionServiceHistoryHandler($serviceReport, $maintenanceLogRepository, $uuidFactory);
  }

  private function command(): RecordInterventionServiceHistoryCommand
  {
    return new RecordInterventionServiceHistoryCommand(
      organizationId: self::ORG_ID,
      interventionId: self::INTERVENTION_ID,
      publicationId: self::PUBLICATION_ID,
      occurredAt: new DateTimeImmutable('2026-07-15T10:00:00+00:00'),
    );
  }
}
