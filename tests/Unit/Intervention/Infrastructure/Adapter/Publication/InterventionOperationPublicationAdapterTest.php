<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Intervention\Application\Contract\Result\InterventionInspectionResult;
use Intervention\Application\Port\Outbound\{InterventionEquipmentReplacementPort, InterventionInspectionResultPort};
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Infrastructure\Adapter\Publication\InterventionOperationPublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Maintenance\Application\Contract\Plan\MaintenanceOperationResult;
use Maintenance\Application\Port\Inbound\MaintenanceOperationResultsPort;
use PHPUnit\Framework\TestCase;

/** Verifies exact occurrence and equipment linkage at the publication boundary. */
final class InterventionOperationPublicationAdapterTest extends TestCase
{
  private const string EQUIPMENT = '550e8400-e29b-41d4-a716-446655441511';

  public function testMaintenanceAcknowledgesOnlyItsExplicitOccurrenceAndActualDate(): void
  {
    $item = $this->item('maintenance');
    $item->operationKind = 'maintenance';
    $maintenance = $this->createMock(MaintenanceOperationResultsPort::class);
    $maintenance->expects(self::once())->method('validateResult')->with(self::callback(static fn (MaintenanceOperationResult $result): bool => self::EQUIPMENT === $result->equipmentId && 'occurrence' === $result->occurrenceId && 'passed' === $result->outcome && '2026-09-30' === $result->performedAt->format('Y-m-d')));
    $maintenance->expects(self::once())->method('acknowledgeResult');
    $this->adapter($item, $maintenance)->publish($this->intervention(), 'organization');
    self::assertSame('validated', $item->executionResult['state'] ?? null);
  }

  public function testAnAdverseClosedControlAcknowledgesPerformance(): void
  {
    $item = $this->item('inspection');
    $item->operationKind = 'control';
    $item->resultResource = '/api/inspections/control';
    $inspections = $this->createStub(InterventionInspectionResultPort::class);
    $inspections->method('find')->willReturn(new InterventionInspectionResult('control', self::EQUIPMENT, 'fail', 'closed', new DateTimeImmutable('2026-09-30T15:00:00+02:00'), 'inspector', 'Defect remains open'));
    $maintenance = $this->createMock(MaintenanceOperationResultsPort::class);
    $maintenance->expects(self::once())->method('validateResult')->with(self::callback(static fn (MaintenanceOperationResult $result): bool => 'control' === $result->operationKind && 'failed' === $result->outcome));
    $maintenance->expects(self::once())->method('acknowledgeResult');
    $this->adapter($item, $maintenance, $inspections)->publish($this->intervention(), 'organization');
    self::assertSame('fail', $item->executionResult['inspectionResult'] ?? null);
  }

  public function testAControlOfAnotherEquipmentNeverAcknowledges(): void
  {
    $item = $this->item('inspection');
    $item->operationKind = 'control';
    $item->resultResource = '/api/inspections/control';
    $inspections = $this->createStub(InterventionInspectionResultPort::class);
    $inspections->method('find')->willReturn(new InterventionInspectionResult('control', 'other', 'pass', 'closed', new DateTimeImmutable(), null, null));
    $maintenance = $this->createMock(MaintenanceOperationResultsPort::class);
    $maintenance->expects(self::never())->method('acknowledgeResult');
    $this->expectException(InterventionValidationException::class);
    $this->adapter($item, $maintenance, $inspections)->publish($this->intervention(), 'organization');
  }

  public function testFailedRepairCannotPublishAsCompleted(): void
  {
    $item = $this->item('repair');
    $item->occurrenceId = null;
    $item->executionResult['outcome'] = 'failed';
    $this->expectException(InterventionValidationException::class);
    $this->adapter($item, $this->createStub(MaintenanceOperationResultsPort::class))->publish($this->intervention(), 'organization');
  }

  public function testSkippedMaintenanceDoesNotAcknowledgeItsOccurrence(): void
  {
    $item = $this->item('maintenance');
    $item->status = 'skipped';
    $maintenance = $this->createMock(MaintenanceOperationResultsPort::class);
    $maintenance->expects(self::never())->method('validateResult');
    $maintenance->expects(self::never())->method('acknowledgeResult');
    $this->adapter($item, $maintenance)->publish($this->intervention(), 'organization');
    self::assertSame('staged', $item->executionResult['state'] ?? null);
  }

  private function item(string $action): InterventionWorkItemRecord
  {
    $item = new InterventionWorkItemRecord();
    $item->id = 'task';
    $item->action = $action;
    $item->status = 'completed';
    $item->target = '/api/equipment/' . self::EQUIPMENT;
    $item->occurrenceId = 'occurrence';
    $item->executionResult = ['equipmentId' => self::EQUIPMENT, 'performedAt' => '2026-09-30T15:00:00+02:00', 'outcome' => 'successful', 'workPerformed' => 'Seal replaced', 'authorId' => 'member', 'state' => 'staged'];

    return $item;
  }

  private function intervention(): InterventionRecord
  {
    $record = new InterventionRecord();
    $record->id = 'intervention';

    return $record;
  }

  private function adapter(InterventionWorkItemRecord $item, MaintenanceOperationResultsPort $maintenance, ?InterventionInspectionResultPort $inspections = null): InterventionOperationPublicationAdapter
  {
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findBy')->willReturn([$item]);
    $manager = $this->createStub(EntityManagerInterface::class);
    $manager->method('getRepository')->willReturn($repository);

    return new InterventionOperationPublicationAdapter($manager, $inspections ?? $this->createStub(InterventionInspectionResultPort::class), $maintenance, $this->createStub(InterventionEquipmentReplacementPort::class));
  }
}
