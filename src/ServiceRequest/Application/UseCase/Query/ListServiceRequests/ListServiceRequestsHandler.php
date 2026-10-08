<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\ListServiceRequests;

use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\Port\Outbound\ServiceRequestRepositoryPort;
use ServiceRequest\Application\Service\ServiceRequestAccessGuard;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function array_map;
use function in_array;
use function mb_strlen;
use function trim;

/** Class ListServiceRequestsHandler. Applies one scoped filter set to page and total. @category UseCase */
final readonly class ListServiceRequestsHandler implements QueryHandler
{
  public function __construct(private ServiceRequestRepositoryPort $requests, private ServiceRequestAccessGuard $access)
  {
  }

  public function __invoke(ListServiceRequestsQuery $query): ListServiceRequestsResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, 'read');
    if ($query->page < 1 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100 || mb_strlen($query->search) > 160 || (null !== $query->status && !in_array($query->status, ['requested', 'qualified', 'rejected', 'cancelled', 'converted'], true))) {
      throw ServiceRequestException::invalid('Invalid request pagination or filters.');
    }

    try {
      foreach ([$query->equipmentId, $query->siteId] as $id) {
        if (null !== $id) {
          Uuid::assertValid($id);
        }
      }
    } catch (InvalidValueException) {
      throw ServiceRequestException::invalid('Invalid target filter.');
    }
    $search = trim($query->search);
    $items = $this->requests->list($query->organizationId, $query->status, $query->equipmentId, $query->siteId, $search, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage);

    return new ListServiceRequestsResult(array_map(ServiceRequestView::fromRequest(...), $items), $this->requests->count($query->organizationId, $query->status, $query->equipmentId, $query->siteId, $search), $query->page, $query->itemsPerPage);
  }
}
