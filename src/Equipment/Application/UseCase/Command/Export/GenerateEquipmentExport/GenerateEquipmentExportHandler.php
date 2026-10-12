<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport;

use Equipment\Application\Port\Outbound\EquipmentCsvRendererPort;
use Equipment\Application\UseCase\Query\ExportEquipmentReport\{ExportEquipmentReportQuery, ExportEquipmentReportResult};
use Equipment\Application\UseCase\Query\ExportEquipments\{ExportEquipmentsQuery, ExportEquipmentsResult};
use Equipment\Application\UseCase\Query\RenderEquipmentLabels\{RenderEquipmentLabelsQuery, RenderEquipmentLabelsResult};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort};

use function base64_encode;
use function sprintf;

/** Generates the complete application artifact while its export audit and receipt share the owning main transaction. @category UseCase */
final readonly class GenerateEquipmentExportHandler implements CommandHandler
{
  public function __construct(private QueryBusPort $queries, private EquipmentCsvRendererPort $csv, private ClockPort $clock, private TransactionManagerPort $transactions)
  {
  }

  public function __invoke(GenerateEquipmentExportCommand $command): GenerateEquipmentExportResult
  {
    return $this->transactions->transactional(fn (): GenerateEquipmentExportResult => $this->generate($command));
  }

  private function generate(GenerateEquipmentExportCommand $command): GenerateEquipmentExportResult
  {
    if ('csv' === $command->format) {
      /** @var ExportEquipmentsResult $result */
      $result = $this->queries->ask(new ExportEquipmentsQuery($command->userId, $command->organizationId));

      return new GenerateEquipmentExportResult(base64_encode($this->csv->render($result->rows)), sprintf('equipments-export-%s.csv', $this->clock->now()->format('Ymd-His')), 'text/csv; charset=utf-8', ['X-Accel-Buffering' => 'no']);
    }
    if ('report' === $command->format) {
      if (null === $command->equipmentId || '' === $command->equipmentId) {
        throw new InvalidArgumentException('An equipment report requires its original equipment identifier.');
      }
      /** @var ExportEquipmentReportResult $result */
      $result = $this->queries->ask(new ExportEquipmentReportQuery($command->userId, $command->organizationId, $command->equipmentId));

      return new GenerateEquipmentExportResult(base64_encode($result->contents), $result->fileName, 'application/pdf');
    }
    if ('labels' === $command->format) {
      /** @var RenderEquipmentLabelsResult $result */
      $result = $this->queries->ask(new RenderEquipmentLabelsQuery($command->userId, $command->organizationId, $command->equipmentIds, $command->facilityId));

      return new GenerateEquipmentExportResult(base64_encode($result->contents), sprintf('equipment-labels-%s.pdf', $this->clock->now()->format('Ymd-His')), 'application/pdf');
    }

    throw new InvalidArgumentException('The equipment export format is unsupported.');
  }
}
