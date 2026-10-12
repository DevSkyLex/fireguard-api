<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport;

use Facility\Application\Port\Outbound\FacilityCsvRendererPort;
use Facility\Application\UseCase\Query\ExportFacilities\{ExportFacilitiesQuery, ExportFacilitiesResult};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{ClockPort, TransactionManagerPort};

use function base64_encode;
use function sprintf;

/** Renders the original CSV inside the owning main transaction containing its export audit and result receipt. @category UseCase */
final readonly class GenerateFacilitiesExportHandler implements CommandHandler
{
  public function __construct(private QueryBusPort $queries, private FacilityCsvRendererPort $csv, private ClockPort $clock, private TransactionManagerPort $transactions)
  {
  }

  public function __invoke(GenerateFacilitiesExportCommand $command): GenerateFacilitiesExportResult
  {
    return $this->transactions->transactional(function () use ($command): GenerateFacilitiesExportResult {
      /** @var ExportFacilitiesResult $result */
      $result = $this->queries->ask(new ExportFacilitiesQuery($command->userId, $command->organizationId, $command->filters));

      return new GenerateFacilitiesExportResult(base64_encode($this->csv->render($result->rows)), sprintf('facilities-export-%s.csv', $this->clock->now()->format('Ymd-His')));
    });
  }
}
