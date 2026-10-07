<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ChangeServiceRequest;

use DateTimeImmutable;
use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\Port\Outbound\{ServiceRequestOriginPort, ServiceRequestRepositoryPort};
use ServiceRequest\Application\Service\{ServiceRequestAccessGuard, ServiceRequestTargetGuard};
use ServiceRequest\Domain\Event\ServiceRequestChangedEvent;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

/** Class ChangeServiceRequestHandler. Locks a request before explicit qualification or terminal decisions. @category UseCase */
final readonly class ChangeServiceRequestHandler implements CommandHandler
{
  public function __construct(private ServiceRequestRepositoryPort $requests, private ServiceRequestAccessGuard $access, private ServiceRequestTargetGuard $targets, private ServiceRequestOriginPort $origins, private ClockPort $clock, private TransactionManagerPort $transactions, private EventDispatcherPort $events)
  {
  }

  public function __invoke(ChangeServiceRequestCommand $command): ChangeServiceRequestResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, 'manage');

    return $this->transactions->transactional(function () use ($command): ChangeServiceRequestResult {
      $request = $this->requests->find($command->requestId, $command->organizationId, true) ?? throw ServiceRequestException::notFound();
      $this->access->assertRevision($request->revision, $command->expectedRevision);
      $now = $this->clock->now();
      $changed = match ($command->action) {
        'patch' => $request->change($command->changes, $now),
        'qualify' => $this->qualify($request, $command, $now),
        'reject' => $request->reject($command->reason ?? '', $now),
        'cancel' => $request->cancel($command->reason ?? '', $now),
        default => throw ServiceRequestException::invalid('Unsupported request action.'),
      };
      if ($changed !== $request) {
        $this->requests->save($changed, $request->revision);
        $this->events->dispatch(new ServiceRequestChangedEvent($changed->organizationId, $changed->id, $command->action, $changed->revision, $changed->updatedAt));
      }

      return new ChangeServiceRequestResult(ServiceRequestView::fromRequest($changed));
    });
  }

  private function qualify(ServiceRequest $request, ChangeServiceRequestCommand $command, DateTimeImmutable $now): ServiceRequest
  {
    if (null !== $request->equipmentId && null !== $command->equipmentId && $command->equipmentId !== $request->equipmentId) {
      throw ServiceRequestException::transitionConflict('The selected equipment cannot replace this request target.');
    }
    $equipmentId = $request->equipmentId ?? $command->equipmentId;
    if (null === $equipmentId) {
      throw ServiceRequestException::invalid('Select the equipment requiring repair before qualification.');
    }
    $current = $this->targets->snapshot($request->organizationId, $equipmentId, $request->siteId);
    $this->origins->assertMatches($request->organizationId, $equipmentId, $request->siteId, $request->originInspectionId, $request->originNonConformityId);
    if (null === $request->equipmentId) {
      $snapshot = $request->targetSnapshot;
      $snapshot['equipment'] = $current['equipment'];
      $request = $request->assignEquipment($equipmentId, $request->siteId, $snapshot, $now);
    }

    return $request->qualify($command->note, $now);
  }
}
