<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\GetServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\Port\Outbound\ServiceRequestRepositoryPort;
use ServiceRequest\Application\Service\ServiceRequestAccessGuard;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Shared\Application\Message\QueryHandler;

/** Class GetServiceRequestHandler. Reads retained history independently of current target lifecycle. @category UseCase */
final readonly class GetServiceRequestHandler implements QueryHandler
{
  public function __construct(private ServiceRequestRepositoryPort $requests, private ServiceRequestAccessGuard $access)
  {
  }

  public function __invoke(GetServiceRequestQuery $query): GetServiceRequestResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, 'read');
    $request = $this->requests->find($query->requestId, $query->organizationId) ?? throw ServiceRequestException::notFound();

    return new GetServiceRequestResult(ServiceRequestView::fromRequest($request));
  }
}
