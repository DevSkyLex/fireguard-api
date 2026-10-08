<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ConvertServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use ServiceRequest\Application\Contract\Work\ServiceRequestWorkRequest;
use ServiceRequest\Application\Port\Outbound\{ServiceRequestRepositoryPort, ServiceRequestWorkPort};
use ServiceRequest\Application\Service\{ServiceRequestAccessGuard, ServiceRequestTargetGuard};
use ServiceRequest\Domain\Event\ServiceRequestChangedEvent;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\ServiceRequestConversionReceipt;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function hash;
use function json_encode;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/** Class ConvertServiceRequestHandler. Fences concurrent conversions and persists work, request and replay receipt atomically. @category UseCase */
final readonly class ConvertServiceRequestHandler implements CommandHandler
{
  public function __construct(private ServiceRequestRepositoryPort $requests, private ServiceRequestAccessGuard $access, private ServiceRequestTargetGuard $targets, private ServiceRequestWorkPort $work, private ClockPort $clock, private TransactionManagerPort $transactions, private EventDispatcherPort $events)
  {
  }

  public function __invoke(ConvertServiceRequestCommand $command): ConvertServiceRequestResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, 'manage');

    return $this->transactions->transactional(function () use ($command): ConvertServiceRequestResult {
      $request = $this->requests->find($command->requestId, $command->organizationId, true) ?? throw ServiceRequestException::notFound();
      if (null === $command->expectedRevision) {
        throw new ServiceRequestException('service_request_precondition_required', 'If-Match is required for conversion.');
      }
      if ($command->expectedRevision < 1) {
        throw ServiceRequestException::stale();
      }
      $intent = $this->intent($command, $request->id);
      $operationId = $intent['operationId'];
      $hash = $intent['hash'];
      $receipt = $this->requests->conversionReceiptForRequest($request->id, $request->organizationId);
      if (null !== $receipt) {
        $this->assertReplay($request, $receipt, $operationId, $hash);

        return new ConvertServiceRequestResult(ServiceRequestView::fromRequest($request));
      }
      if (null !== $this->requests->conversionReceiptByOperation($request->organizationId, $operationId)) {
        throw ServiceRequestException::operationConflict();
      }
      $this->access->assertRevision($request->revision, $command->expectedRevision);
      if ('qualified' !== $request->status || null === $request->equipmentId) {
        throw ServiceRequestException::transitionConflict('Only qualified equipment repair requests can be converted.');
      }
      $selection = new ServiceRequestWorkRequest($request->organizationId, $request->id, $command->actorId, $request->equipmentId, $request->siteId, $request->title, $request->description, $intent['interventionId'], $intent['taskId'], $operationId);
      $this->work->reserveSelection($selection);
      $this->targets->snapshot($request->organizationId, $request->equipmentId, $request->siteId);
      $link = $this->work->createOrLink($selection);
      $now = $this->clock->now();
      $converted = $request->convert($link->interventionId, $link->taskId, $now);
      $this->requests->save($converted, $request->revision);
      $this->requests->saveConversionReceipt(new ServiceRequestConversionReceipt($converted->organizationId, $converted->id, $operationId, $hash, $link->interventionId, $link->taskId, $now));
      $this->events->dispatch(new ServiceRequestChangedEvent($converted->organizationId, $converted->id, 'converted', $converted->revision, $converted->updatedAt));

      return new ConvertServiceRequestResult(ServiceRequestView::fromRequest($converted));
    });
  }

  /**
   * Method intent
   *
   * Receipt identity includes the canonical explicit work selection in its original key order.
   *
   * @access private
   *
   * @param ConvertServiceRequestCommand $command requested operation and work selection
   * @param string $requestId locked request identity
   *
   * @return array{operationId:string,interventionId:string|null,taskId:string|null,hash:string} canonical conversion intent
   */
  private function intent(ConvertServiceRequestCommand $command, string $requestId): array
  {
    $operationId = $this->uuid($command->clientOperationId);
    $interventionId = null === $command->existingInterventionId ? null : $this->uuid($command->existingInterventionId);
    $taskId = null === $command->existingTaskId ? null : $this->uuid($command->existingTaskId);
    if (null !== $taskId && null === $interventionId) {
      throw ServiceRequestException::invalid('An existing task requires its intervention identifier.');
    }
    $hash = hash('sha256', json_encode(['requestId' => $requestId, 'existingInterventionId' => $interventionId, 'existingTaskId' => $taskId], JSON_THROW_ON_ERROR));

    return ['operationId' => $operationId, 'interventionId' => $interventionId, 'taskId' => $taskId, 'hash' => $hash];
  }

  /**
   * Method assertReplay
   *
   * Matching committed receipts bypass current target checks without admitting a changed key or work link.
   *
   * @access private
   *
   * @param ServiceRequest $request locked retained request
   * @param ServiceRequestConversionReceipt $receipt original committed conversion
   * @param string $operationId canonical operation key
   * @param string $hash canonical work selection fingerprint
   *
   * @return void rejects incompatible or inconsistent replay
   */
  private function assertReplay(ServiceRequest $request, ServiceRequestConversionReceipt $receipt, string $operationId, string $hash): void
  {
    if ($receipt->clientOperationId !== $operationId || $receipt->payloadHash !== $hash || 'converted' !== $request->status || $request->interventionId !== $receipt->interventionId || $request->taskId !== $receipt->taskId) {
      throw ServiceRequestException::operationConflict();
    }
  }

  private function uuid(string $value): string
  {
    try {
      Uuid::assertValid($value);
    } catch (InvalidValueException) {
      throw ServiceRequestException::invalid('Invalid conversion identifier.');
    }

    return strtolower($value);
  }
}
