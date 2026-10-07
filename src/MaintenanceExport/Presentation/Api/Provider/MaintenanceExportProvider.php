<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport\{ReadMaintenanceExportQuery,ReadMaintenanceExportResult};
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Presentation\Api\Dto\Output\{MaintenanceExportOutput,MaintenanceExportReferenceOutput,MaintenanceExportSourceOutput};
use MaintenanceExport\Presentation\Api\Operation\MaintenanceExportOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{RequestStack,Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Class MaintenanceExportProvider
 * Saved-file reads return a Response so API Platform never serializes or regenerates retained bytes.
 *
 * @category Provider
 *
 * @implements ProviderInterface<MaintenanceExportOutput|MaintenanceExportReferenceOutput|MaintenanceExportSourceOutput|Response>
 */
final readonly class MaintenanceExportProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   *
   * @param array<string,mixed> $uriVariables route scope
   * @param array<string,mixed> $context serializer context
   *
   * @return MaintenanceExportOutput|MaintenanceExportReferenceOutput|MaintenanceExportSourceOutput|Response|TraversablePaginator<MaintenanceExportOutput|MaintenanceExportReferenceOutput|MaintenanceExportSourceOutput> authorized data or immutable bytes
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MaintenanceExportOutput|MaintenanceExportReferenceOutput|MaintenanceExportSourceOutput|Response|TraversablePaginator
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $action = match($operation->getName()) {
      MaintenanceExportOperations::EXPORTS => 'exports',MaintenanceExportOperations::EXPORT => 'export',MaintenanceExportOperations::SOURCES => 'sources',MaintenanceExportOperations::REFERENCES => 'references',MaintenanceExportOperations::FILE => 'file',default => throw MaintenanceExportException::invalid('Unknown export read.')
    };
    $request = $this->requests->getCurrentRequest();
    /**
     * @var ReadMaintenanceExportResult $result
     */
    $result = $this->queries->ask(new ReadMaintenanceExportQuery($actor, $this->string($uriVariables, 'organizationId') ?? '', $action, $this->string($uriVariables, 'id'), $this->string($uriVariables, 'format'), $request?->query->getString('system'), $request?->query->getString('resourceType'), $request?->query->getString('resourceId'), $request?->query->getInt('page', 1) ?? 1, $request?->query->getInt('itemsPerPage', 30) ?? 30, $request?->query->getString('search')));
    if ('file' === $result->kind) {
      return new Response($result->bytes, 200, ['Content-Type' => $result->mediaType . '; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $result->fileName . '"', 'ETag' => '"sha256-' . $result->sha256 . '"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
    $items = [];
    foreach ($result->items as $item) {
      $items[] = match($result->kind) {
        'source' => MaintenanceExportSourceOutput::fromProjection($item),'reference' => MaintenanceExportReferenceOutput::fromProjection($item),default => MaintenanceExportOutput::fromProjection($item)
      };
    }
    if ($result->collection) {
      return new TraversablePaginator(new ArrayIterator($items), (float) $result->page, (float) $result->itemsPerPage, (float) $result->total);
    }

    return $items[0] ?? throw MaintenanceExportException::notFound();
  }

  /**
   * Method string
   *
   * @param array<string,mixed> $data route variables
   *
   * @return string|null optional scoped primitive
   */
  private function string(array $data, string $field): ?string
  {
    return is_string($data[$field] ?? null) ? $data[$field] : null;
  }
  // #endregion
}
