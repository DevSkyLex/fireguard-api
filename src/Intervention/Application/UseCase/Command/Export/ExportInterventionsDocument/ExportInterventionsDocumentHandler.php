<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument;

use DateTimeImmutable;
use Intervention\Application\Contract\Event\Export\InterventionsExportedEvent;
use Intervention\Application\Contract\Export\InterventionExportDocument;
use Intervention\Application\Port\Outbound\InterventionCsvRendererPort;
use Intervention\Application\UseCase\Query\ExportInterventions\{ExportInterventionsQuery, ExportInterventionsResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_keys;
use function base64_encode;
use function sprintf;

/** Renders the original document and records its audit intent inside the same owning main transaction. @category UseCase */
final readonly class ExportInterventionsDocumentHandler implements CommandHandler
{
  public function __construct(
    private QueryBusPort $queryBus,
    private InterventionCsvRendererPort $csvRenderer,
    private EventDispatcherPort $events,
    private TransactionManagerPort $transactions,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  public function __invoke(ExportInterventionsDocumentCommand $command): InterventionExportDocument
  {
    return $this->transactions->transactional(fn (): InterventionExportDocument => $this->export($command));
  }

  private function export(ExportInterventionsDocumentCommand $command): InterventionExportDocument
  {
    $organizationId = $command->organizationId;
    $this->authorize($command->userId, $organizationId);
    $filters = $command->filters;
    /** @var ExportInterventionsResult $result */
    $result = $this->queryBus->ask(new ExportInterventionsQuery(
      userId: $command->userId,
      organizationId: $organizationId,
      filters: $filters,
    ));


    $document = $this->csvRenderer->render($result->rows);

    $this->events->dispatch(new InterventionsExportedEvent(
      organizationId: $organizationId,
      actorUserId: $command->userId,
      format: 'csv',
      rowCount: $result->total,
      filterKeys: array_keys($filters),
    ));

    $fileName = sprintf('interventions-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

    return new InterventionExportDocument(base64_encode($document), $fileName, 'text/csv; charset=utf-8', $organizationId);

  }

  private function authorize(string $userId, string $organizationId): void
  {
    $decision = $this->authorization->resolveAccess($userId, $organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw new InterventionNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }
  }
}
