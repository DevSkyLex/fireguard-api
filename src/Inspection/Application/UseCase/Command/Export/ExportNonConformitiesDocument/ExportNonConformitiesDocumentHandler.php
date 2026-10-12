<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument;

use DateTimeImmutable;
use Inspection\Application\Contract\Event\Export\NonConformitiesExportedEvent;
use Inspection\Application\Contract\Export\InspectionExportDocument;
use Inspection\Application\Port\Outbound\NonConformityCsvRendererPort;
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_keys;
use function base64_encode;
use function sprintf;

/** Renders the original document and records its audit intent inside the same owning main transaction. @category UseCase */
final readonly class ExportNonConformitiesDocumentHandler implements CommandHandler
{
  public function __construct(
    private QueryBusPort $queryBus,
    private NonConformityCsvRendererPort $csvRenderer,
    private EventDispatcherPort $events,
    private TransactionManagerPort $transactions,
  ) {
  }

  public function __invoke(ExportNonConformitiesDocumentCommand $command): InspectionExportDocument
  {
    return $this->transactions->transactional(fn (): InspectionExportDocument => $this->export($command));
  }

  private function export(ExportNonConformitiesDocumentCommand $command): InspectionExportDocument
  {
    $organizationId = $command->organizationId;
    $filters = $command->filters;
    /** @var ExportNonConformitiesResult $result */
    $result = $this->queryBus->ask(new ExportNonConformitiesQuery(
      userId: $command->userId,
      organizationId: $organizationId,
      filters: $filters,
    ));


    $document = $this->csvRenderer->render($result->rows);

    $this->events->dispatch(new NonConformitiesExportedEvent(
      organizationId: $organizationId,
      actorUserId: $command->userId,
      format: 'csv',
      rowCount: $result->total,
      filterKeys: array_keys($filters),
    ));

    $fileName = sprintf('non-conformities-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

    return new InspectionExportDocument(base64_encode($document), $fileName, 'text/csv; charset=utf-8', $organizationId);

  }
}
