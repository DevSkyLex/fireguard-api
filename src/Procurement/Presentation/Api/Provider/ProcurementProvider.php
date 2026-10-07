<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Procurement\Application\UseCase\Query\ReadProcurement\{ReadProcurementQuery, ReadProcurementResult};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Presentation\Api\Dto\Output\{ProcurementReceiptOutput, ProcurementReturnOutput, PurchaseOrderOutput, SupplierOutput};
use Procurement\Presentation\Api\Operation\ProcurementOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/** Organization and monetary permission checks stay in the query handler.
 * @implements ProviderInterface<SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput>
 */
final readonly class ProcurementProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables
   * @param array<string,mixed> $context
   *
   * @return SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput|TraversablePaginator<SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput|TraversablePaginator
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $action = match ($operation->getName()) {
      ProcurementOperations::SUPPLIERS => 'suppliers', ProcurementOperations::SUPPLIER => 'supplier',
      ProcurementOperations::ORDERS => 'orders', ProcurementOperations::ORDER => 'order',
      ProcurementOperations::RECEIPTS => 'receipts', ProcurementOperations::RECEIPT => 'receipt',
      ProcurementOperations::RETURNS => 'returns', ProcurementOperations::RETURN_DETAIL => 'return',
      default => throw ProcurementException::invalid('Unknown procurement HTTP read.'),
    };
    $request = $this->requests->getCurrentRequest();
    $supplier = $request?->query->getString('supplierId');
    $status = $request?->query->getString('status');
    $archived = 'all' === $request?->query->getString('archived') ? null : ($request?->query->getBoolean('archived', false) ?? false);
    /** @var ReadProcurementResult $result */
    $result = $this->queries->ask(new ReadProcurementQuery($actorId, $this->identifier($uriVariables, 'organizationId') ?? '', $action, $this->identifier($uriVariables, 'id'), $request?->query->getString('search', '') ?? '', $archived, null === $status || '' === $status ? null : $status, null === $supplier || '' === $supplier ? null : $supplier, $request?->query->getInt('page', 1) ?? 1, $request?->query->getInt('itemsPerPage', 30) ?? 30));
    $items = [];
    foreach ($result->items as $data) {
      $items[] = match ($result->kind) {
        'supplier' => SupplierOutput::fromProjection($data), 'order' => PurchaseOrderOutput::fromProjection($data),
        'receipt' => ProcurementReceiptOutput::fromProjection($data),
        'return' => ProcurementReturnOutput::fromProjection($data),
        default => throw ProcurementException::invalid('Invalid procurement read result.'),
      };
    }
    if ($result->collection) {
      return new TraversablePaginator(new ArrayIterator($items), (float) $result->page, (float) $result->itemsPerPage, (float) $result->total);
    }

    return $items[0] ?? throw ProcurementException::notFound();
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function identifier(array $variables, string $field): ?string
  {
    return is_string($variables[$field] ?? null) ? $variables[$field] : null;
  }
}
