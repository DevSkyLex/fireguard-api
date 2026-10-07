<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Query\ReadProcurement;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Domain\Exception\ProcurementException;
use Shared\Application\Message\QueryHandler;

use function in_array;
use function max;
use function min;
use function strtolower;

/** Authorizes tenant scope before loading rows and hides financial fields independently. */
final readonly class ReadProcurementHandler implements QueryHandler
{
  public function __construct(private ProcurementRepositoryPort $repository, private OrganizationAuthorizationPort $authorization, private ProcurementProjection $projection)
  {
  }

  public function __invoke(ReadProcurementQuery $query): ReadProcurementResult
  {
    $query = new ReadProcurementQuery(strtolower($query->actorId), strtolower($query->organizationId), $query->action, null === $query->id ? null : strtolower($query->id), $query->search, $query->archived, $query->status, null === $query->supplierId ? null : strtolower($query->supplierId), $query->page, $query->itemsPerPage);
    $decision = $this->authorization->resolveAccess($query->actorId, $query->organizationId, 'organization.procurement.read');
    if (OrganizationAccessDecision::GRANTED !== $decision) {
      throw OrganizationAccessDecision::OUTSIDE_SCOPE === $decision ? ProcurementException::notFound() : ProcurementException::denied();
    }
    $finance = $this->authorization->hasPermission($query->actorId, $query->organizationId, 'organization.maintenance_cost.read');
    $page = max(1, $query->page);
    $limit = min(100, max(1, $query->itemsPerPage));
    $offset = ($page - 1) * $limit;
    $items = [];
    $total = 1;
    switch ($query->action) {
      case 'suppliers':
        foreach ($this->repository->suppliers($query->organizationId, $query->search, $query->archived, $offset, $limit) as $supplier) {
          $items[] = $this->projection->supplier($supplier);
        }
        $total = $this->repository->countSuppliers($query->organizationId, $query->search, $query->archived);
        $kind = 'supplier';

        break;
      case 'supplier':
        $items[] = $this->projection->supplier($this->repository->supplier($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound());
        $kind = 'supplier';

        break;
      case 'orders':
        foreach ($this->repository->orders($query->organizationId, $query->status, $query->supplierId, $offset, $limit) as $order) {
          $items[] = $this->projection->order($order, $finance);
        }
        $total = $this->repository->countOrders($query->organizationId, $query->status, $query->supplierId);
        $kind = 'order';

        break;
      case 'order':
        $items[] = $this->projection->order($this->repository->order($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound(), $finance);
        $kind = 'order';

        break;
      case 'receipts':
        $order = $this->repository->order($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound();
        foreach ($this->repository->receipts($query->organizationId, $order->id, $offset, $limit) as $receipt) {
          $items[] = $this->projection->receipt($receipt, $finance);
        }
        $total = $this->repository->countReceipts($query->organizationId, $order->id);
        $kind = 'receipt';

        break;
      case 'receipt':
        $items[] = $this->projection->receipt($this->repository->receipt($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound(), $finance);
        $kind = 'receipt';

        break;
      case 'returns':
        $receipt = $this->repository->receipt($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound();
        foreach ($this->repository->returns($query->organizationId, $receipt->id, $offset, $limit) as $return) {
          $items[] = $this->projection->returnDeclaration($return);
        }
        $total = $this->repository->countReturns($query->organizationId, $receipt->id);
        $kind = 'return';

        break;
      case 'return':
        $items[] = $this->projection->returnDeclaration($this->repository->returnDeclaration($query->organizationId, $query->id ?? '') ?? throw ProcurementException::notFound());
        $kind = 'return';

        break;
      default:
        throw ProcurementException::invalid('Unknown procurement read.');
    }

    return new ReadProcurementResult($kind, $items, !in_array($query->action, ['supplier', 'order', 'receipt', 'return'], true), $total, $page, $limit);
  }
}
