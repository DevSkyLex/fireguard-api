<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\CreateServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\Port\Outbound\{ServiceRequestOriginPort, ServiceRequestRepositoryPort};
use ServiceRequest\Application\Service\{ServiceRequestAccessGuard, ServiceRequestTargetGuard};
use ServiceRequest\Domain\Event\ServiceRequestChangedEvent;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};

/** Class CreateServiceRequestHandler. Records a validated repair need and origin in one main work unit. @category UseCase */
final readonly class CreateServiceRequestHandler implements CommandHandler
{
  public function __construct(private ServiceRequestRepositoryPort $requests, private ServiceRequestAccessGuard $access, private ServiceRequestTargetGuard $targets, private ServiceRequestOriginPort $origins, private ClockPort $clock, private UuidGeneratorPort $ids, private TransactionManagerPort $transactions, private EventDispatcherPort $events)
  {
  }

  public function __invoke(CreateServiceRequestCommand $command): CreateServiceRequestResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, 'create');

    return $this->transactions->transactional(function () use ($command): CreateServiceRequestResult {
      $snapshot = $this->targets->snapshot($command->organizationId, $command->equipmentId, $command->siteId);
      $siteId = $snapshot['site']['id'] ?? null;
      $this->origins->assertMatches($command->organizationId, $command->equipmentId, $siteId, $command->originInspectionId, $command->originNonConformityId);
      $request = ServiceRequest::create($this->ids->generate(), $command->organizationId, new ServiceRequestTarget($command->equipmentId, $siteId, $snapshot, $command->originInspectionId, $command->originNonConformityId), new ServiceRequestContent($command->title, $command->description, $command->priority), $this->clock->now());
      $this->requests->save($request);
      $this->events->dispatch(new ServiceRequestChangedEvent($request->organizationId, $request->id, 'requested', $request->revision, $request->updatedAt));

      return new CreateServiceRequestResult(ServiceRequestView::fromRequest($request));
    });
  }
}
