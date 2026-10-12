<?php

declare(strict_types=1);

namespace Tests\Support\Export;

use DateTimeImmutable;
use Equipment\Application\Service\Export\EquipmentExportCsvSchema;
use Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\{GenerateEquipmentExportCommand, GenerateEquipmentExportHandler};
use Equipment\Infrastructure\Adapter\Export\EquipmentCsvRendererAdapter;
use Facility\Application\Service\Export\FacilityExportCsvSchema;
use Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport\{GenerateFacilitiesExportCommand, GenerateFacilitiesExportHandler};
use Facility\Infrastructure\Adapter\Export\FacilityCsvRendererAdapter;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort};
use Shared\Infrastructure\Csv\SpreadsheetSafeTextAdapter;

/**
 * Exercises the real owner artifact command from existing controller fixtures.
 *
 * @category Test Support
 *
 * @phpstan-require-extends TestCase
 */
trait ExportArtifactCommandBusTrait
{
  private function equipmentExportCommands(QueryBusPort $queries): CommandBusPort
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-11T12:00:00Z'));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());
    $handler = new GenerateEquipmentExportHandler($queries, new EquipmentCsvRendererAdapter(new EquipmentExportCsvSchema(new SpreadsheetSafeTextAdapter())), $clock, $transactions);
    $commands = $this->createStub(CommandBusPort::class);
    $commands->method('dispatch')->willReturnCallback(static fn (GenerateEquipmentExportCommand $command): object => $handler($command));

    return $commands;
  }

  private function facilityExportCommands(QueryBusPort $queries): CommandBusPort
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-11T12:00:00Z'));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());
    $handler = new GenerateFacilitiesExportHandler($queries, new FacilityCsvRendererAdapter(new FacilityExportCsvSchema(new SpreadsheetSafeTextAdapter())), $clock, $transactions);
    $commands = $this->createStub(CommandBusPort::class);
    $commands->method('dispatch')->willReturnCallback(static fn (GenerateFacilitiesExportCommand $command): object => $handler($command));

    return $commands;
  }
}
