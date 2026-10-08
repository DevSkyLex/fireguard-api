<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceRequest\Application\UseCase\Command\ConvertServiceRequest;

use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ServiceRequest\Application\Contract\Target\{ServiceRequestEquipmentTarget, ServiceRequestSiteTarget};
use ServiceRequest\Application\Contract\Work\{ServiceRequestWorkLink, ServiceRequestWorkRequest};
use ServiceRequest\Application\Port\Outbound\{ServiceRequestEquipmentTargetPort, ServiceRequestRepositoryPort, ServiceRequestSiteTargetPort, ServiceRequestWorkPort};
use ServiceRequest\Application\Service\{ServiceRequestAccessGuard, ServiceRequestTargetGuard};
use ServiceRequest\Application\UseCase\Command\ConvertServiceRequest\{ConvertServiceRequestCommand, ConvertServiceRequestHandler};
use ServiceRequest\Domain\Event\ServiceRequestChangedEvent;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use ServiceRequest\Domain\ValueObject\ServiceRequestConversionReceipt;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

use function hash;
use function json_encode;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * Class ConvertServiceRequestHandlerTest
 *
 * Proves authorized, transactional conversion and side-effect-free receipt replay.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConvertServiceRequestHandlerTest extends TestCase
{
  private const string ACTOR = 'baf10000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'baf10000-0000-4000-8000-000000000002';

  private const string REQUEST = 'baf10000-0000-4000-8000-000000000003';

  private const string EQUIPMENT = 'baf10000-0000-4000-8000-000000000004';

  private const string SITE = 'baf10000-0000-4000-8000-000000000005';

  private const string FACILITY = 'baf10000-0000-4000-8000-000000000006';

  private const string INTERVENTION = 'baf10000-0000-4000-8000-000000000007';

  private const string TASK = 'baf10000-0000-4000-8000-000000000008';

  private const string OPERATION = 'baf10000-0000-4000-8000-000000000009';

  private const string OTHER_REQUEST = 'baf10000-0000-4000-8000-000000000010';

  private ServiceRequestRepositoryPort&MockObject $requests;

  private OrganizationAuthorizationPort&MockObject $authorization;

  private ServiceRequestEquipmentTargetPort&MockObject $equipment;

  private ServiceRequestSiteTargetPort&MockObject $sites;

  private ServiceRequestWorkPort&MockObject $work;

  private ClockPort&MockObject $clock;

  private TransactionManagerPort&MockObject $transactions;

  private EventDispatcherPort&MockObject $events;

  private bool $transactionActive = false;

  private bool $targetLocked = false;

  private bool $selectionReserved = false;

  // #region Methods
  protected function setUp(): void
  {
    $this->requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $this->authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $this->equipment = $this->createMock(ServiceRequestEquipmentTargetPort::class);
    $this->sites = $this->createMock(ServiceRequestSiteTargetPort::class);
    $this->work = $this->createMock(ServiceRequestWorkPort::class);
    $this->clock = $this->createMock(ClockPort::class);
    $this->transactions = $this->createMock(TransactionManagerPort::class);
    $this->events = $this->createMock(EventDispatcherPort::class);
    $this->events->expects(self::never())->method('dispatchAll');
  }

  /**
   * @return iterable<string, array{OrganizationAccessDecision, string}>
   */
  public static function deniedAccess(): iterable
  {
    yield 'missing manage permission' => [OrganizationAccessDecision::MISSING_PERMISSION, 'service_request_access_denied'];
    yield 'outside organization' => [OrganizationAccessDecision::OUTSIDE_SCOPE, 'service_request_not_found'];
  }

  #[Test]
  #[DataProvider('deniedAccess')]
  public function checksManageAccessBeforeOpeningATransactionOrReadingTheRequest(OrganizationAccessDecision $decision, string $reason): void
  {
    $this->authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.service_requests.manage')->willReturn($decision);
    $this->transactions->expects(self::never())->method('transactional');
    $this->requests->expects(self::never())->method('find');
    $this->noEffects();
    $this->noTargetReads();
    self::assertReason($reason, fn () => $this->handler()(self::command()));
  }

  #[Test]
  public function convertsQualifiedWorkWithNormalizedReferencesAndAnExactReceipt(): void
  {
    $request = self::qualified();
    $now = self::now()->modify('+2 hours');
    $this->allowAccess();
    $this->transaction();
    $this->find($request);
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->with(self::REQUEST, self::ORGANIZATION)->willReturn(null);
    $this->requests->expects(self::once())->method('conversionReceiptByOperation')->with(self::ORGANIZATION, self::OPERATION)->willReturn(null);
    $this->currentTarget();
    $effects = [];
    $this->work->expects(self::once())->method('createOrLink')->willReturnCallback(function (ServiceRequestWorkRequest $work) use (&$effects): ServiceRequestWorkLink {
      self::assertTrue($this->transactionActive);
      self::assertSame(self::ORGANIZATION, $work->organizationId);
      self::assertSame(self::REQUEST, $work->requestId);
      self::assertSame(self::ACTOR, $work->actorId);
      self::assertSame(self::EQUIPMENT, $work->equipmentId);
      self::assertSame(self::SITE, $work->siteId);
      self::assertSame('Repair extinguisher', $work->title);
      self::assertSame('Pressure gauge damaged', $work->description);
      self::assertSame(self::INTERVENTION, $work->existingInterventionId);
      self::assertSame(self::TASK, $work->existingTaskId);
      self::assertSame(self::OPERATION, $work->clientOperationId);
      $effects[] = 'work';

      return new ServiceRequestWorkLink(self::INTERVENTION, self::TASK, false);
    });
    $this->clock->expects(self::once())->method('now')->willReturn($now);
    $this->requests->expects(self::once())->method('save')->willReturnCallback(function (ServiceRequest $saved, ?int $revision) use ($request, $now, &$effects): void {
      self::assertTrue($this->transactionActive);
      self::assertSame(2, $revision);
      self::assertEquals($request->convert(self::INTERVENTION, self::TASK, $now), $saved);
      $effects[] = 'save';
    });
    $this->requests->expects(self::once())->method('saveConversionReceipt')->willReturnCallback(function (ServiceRequestConversionReceipt $receipt) use ($now, &$effects): void {
      self::assertTrue($this->transactionActive);
      self::assertSame(self::ORGANIZATION, $receipt->organizationId);
      self::assertSame(self::REQUEST, $receipt->requestId);
      self::assertSame(self::OPERATION, $receipt->clientOperationId);
      self::assertSame(self::fingerprint(self::INTERVENTION, self::TASK), $receipt->payloadHash);
      self::assertSame(self::INTERVENTION, $receipt->interventionId);
      self::assertSame(self::TASK, $receipt->taskId);
      self::assertSame($now, $receipt->createdAt);
      $effects[] = 'receipt';
    });
    $this->events->expects(self::once())->method('dispatch')->willReturnCallback(function (object $event) use ($now, &$effects): void {
      self::assertTrue($this->transactionActive);
      self::assertInstanceOf(ServiceRequestChangedEvent::class, $event);
      self::assertSame(self::ORGANIZATION, $event->organizationId);
      self::assertSame(self::REQUEST, $event->requestId);
      self::assertSame('converted', $event->change);
      self::assertSame(3, $event->revision);
      self::assertSame($now, $event->occurredAt);
      $effects[] = 'event';
    });
    $result = $this->handler()(self::command(existingInterventionId: strtoupper(self::INTERVENTION), existingTaskId: strtoupper(self::TASK)));

    self::assertSame(['work', 'save', 'receipt', 'event'], $effects);
    self::assertFalse($this->transactionActive);
    self::assertSame(self::REQUEST, $result->request->id);
    self::assertSame(self::ORGANIZATION, $result->request->organizationId);
    self::assertSame(self::EQUIPMENT, $result->request->equipmentId);
    self::assertSame(self::SITE, $result->request->siteId);
    self::assertSame('Repair extinguisher', $result->request->title);
    self::assertSame('Pressure gauge damaged', $result->request->description);
    self::assertSame('normal', $result->request->priority);
    self::assertSame('converted', $result->request->status);
    self::assertSame(3, $result->request->revision);
    self::assertSame(self::INTERVENTION, $result->request->interventionId);
    self::assertSame(self::TASK, $result->request->taskId);
    self::assertSame($now, $result->request->convertedAt);
    self::assertSame($now, $result->request->updatedAt);
    self::assertSame($request->requestedAt, $result->request->requestedAt);
    self::assertSame($request->qualifiedAt, $result->request->qualifiedAt);
    self::assertSame('Repair approved', $result->request->qualificationNote);
    self::assertNull($result->request->originInspectionId);
    self::assertNull($result->request->originNonConformityId);
    self::assertNull($result->request->decisionReason);
    self::assertNull($result->request->rejectedAt);
    self::assertNull($result->request->cancelledAt);
    self::assertSame($request->targetSnapshot, $result->request->targetSnapshot);
  }

  #[Test]
  public function replaysTheReceiptWithAnOldPositiveRevisionWithoutCurrentTargetReads(): void
  {
    $request = self::qualified()->convert(self::INTERVENTION, self::TASK, self::now()->modify('+2 hours'));
    $this->allowAccess();
    $this->transaction();
    $this->find($request);
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->with(self::REQUEST, self::ORGANIZATION)->willReturn(self::receipt(self::fingerprint(self::INTERVENTION, self::TASK)));
    $this->requests->expects(self::never())->method('conversionReceiptByOperation');
    $this->noEffects();
    $this->noTargetReads();
    $result = $this->handler()(self::command(1, strtoupper(self::INTERVENTION), strtoupper(self::TASK)));

    self::assertSame('converted', $result->request->status);
    self::assertSame(3, $result->request->revision);
    self::assertSame(self::INTERVENTION, $result->request->interventionId);
    self::assertSame(self::TASK, $result->request->taskId);
    self::assertSame($request->updatedAt, $result->request->updatedAt);
    self::assertSame($request->targetSnapshot, $result->request->targetSnapshot);
    self::assertFalse($this->transactionActive);
  }

  #[Test]
  public function refusesReusingTheSameOperationWithAnotherBody(): void
  {
    $request = self::qualified()->convert(self::INTERVENTION, self::TASK, self::now()->modify('+2 hours'));
    $this->allowAccess();
    $this->transaction();
    $this->find($request);
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->willReturn(self::receipt(self::fingerprint(null, null)));
    $this->requests->expects(self::never())->method('conversionReceiptByOperation');
    $this->noEffects();
    $this->noTargetReads();
    self::assertReason('service_request_operation_conflict', fn () => $this->handler()(self::command(1, self::INTERVENTION)));
    self::assertFalse($this->transactionActive);
  }

  #[Test]
  public function refusesAnOperationThatAlreadyConvertedAnotherRequest(): void
  {
    $this->allowAccess();
    $this->transaction();
    $this->find(self::qualified());
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->willReturn(null);
    $this->requests->expects(self::once())->method('conversionReceiptByOperation')->with(self::ORGANIZATION, self::OPERATION)->willReturn(self::receipt(self::fingerprint(null, null), self::OTHER_REQUEST));
    $this->noEffects();
    $this->noTargetReads();
    self::assertReason('service_request_operation_conflict', fn () => $this->handler()(self::command()));
  }

  /**
   * @return iterable<string, array{?int, string, bool}>
   */
  public static function failedPreconditions(): iterable
  {
    yield 'absent revision' => [null, 'service_request_precondition_required', false];
    yield 'zero revision' => [0, 'service_request_revision_stale', false];
    yield 'negative revision' => [-1, 'service_request_revision_stale', false];
    yield 'stale positive revision' => [1, 'service_request_revision_stale', true];
  }

  #[Test]
  #[DataProvider('failedPreconditions')]
  public function refusesMissingOrStalePreconditionsBeforeAnyWork(?int $revision, string $reason, bool $checksReceipt): void
  {
    $this->allowAccess();
    $this->transaction();
    $this->find(self::qualified());
    $this->requests->expects($checksReceipt ? self::once() : self::never())->method('conversionReceiptForRequest')->willReturn(null);
    $this->requests->expects($checksReceipt ? self::once() : self::never())->method('conversionReceiptByOperation')->willReturn(null);
    $this->noEffects();
    $this->noTargetReads();
    self::assertReason($reason, fn () => $this->handler()(self::command($revision)));
  }

  #[Test]
  public function requiresQualificationBeforeCreatingRepairWork(): void
  {
    $this->allowAccess();
    $this->transaction();
    $this->find(self::requested());
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->willReturn(null);
    $this->requests->expects(self::once())->method('conversionReceiptByOperation')->willReturn(null);
    $this->noEffects();
    $this->noTargetReads();
    self::assertReason('service_request_transition_conflict', fn () => $this->handler()(self::command(1)));
  }

  #[Test]
  public function propagatesWorkFailureInsideTheTransactionWithoutSavingOrDispatching(): void
  {
    $this->allowAccess();
    $this->transaction();
    $this->find(self::qualified());
    $this->requests->expects(self::once())->method('conversionReceiptForRequest')->willReturn(null);
    $this->requests->expects(self::once())->method('conversionReceiptByOperation')->willReturn(null);
    $this->currentTarget();
    $failure = new RuntimeException('Intervention work rejected');
    $this->work->expects(self::once())->method('createOrLink')->willReturnCallback(function (ServiceRequestWorkRequest $request) use ($failure): never {
      self::assertTrue($this->transactionActive);
      self::assertSame(self::REQUEST, $request->requestId);

      throw $failure;
    });
    $this->requests->expects(self::never())->method('save');
    $this->requests->expects(self::never())->method('saveConversionReceipt');
    $this->events->expects(self::never())->method('dispatch');
    $this->clock->expects(self::never())->method('now');

    try {
      $this->handler()(self::command());
      self::fail('A work failure must propagate from the transaction callback.');
    } catch (RuntimeException $exception) {
      self::assertSame($failure, $exception);
    }
    self::assertFalse($this->transactionActive);
  }

  private function allowAccess(): void
  {
    $this->authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.service_requests.manage')->willReturnCallback(function (): OrganizationAccessDecision {
      self::assertFalse($this->transactionActive);

      return OrganizationAccessDecision::GRANTED;
    });
  }

  private function transaction(): void
  {
    $this->transactions->expects(self::once())->method('transactional')->willReturnCallback(function (callable $operation): mixed {
      self::assertFalse($this->transactionActive);
      $this->transactionActive = true;

      try {
        return $operation();
      } finally {
        $this->transactionActive = false;
      }
    });
  }

  private function find(ServiceRequest $request): void
  {
    $this->requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturnCallback(function () use ($request): ServiceRequest {
      self::assertTrue($this->transactionActive);

      return $request;
    });
  }

  private function currentTarget(): void
  {
    $this->work->expects(self::once())->method('reserveSelection')->willReturnCallback(function (ServiceRequestWorkRequest $selection): void {
      self::assertTrue($this->transactionActive);
      self::assertFalse($this->targetLocked);
      self::assertSame(self::ORGANIZATION, $selection->organizationId);
      self::assertSame(self::REQUEST, $selection->requestId);
      $this->selectionReserved = true;
    });
    $this->sites->expects(self::once())->method('lock')->with(self::ORGANIZATION)->willReturnCallback(function (): void {
      self::assertTrue($this->transactionActive);
      self::assertTrue($this->selectionReserved);
      $this->targetLocked = true;
    });
    $this->equipment->expects(self::once())->method('find')->with(self::EQUIPMENT, self::ORGANIZATION)->willReturnCallback(function (): ServiceRequestEquipmentTarget {
      self::assertTrue($this->transactionActive);
      self::assertTrue($this->targetLocked);

      return new ServiceRequestEquipmentTarget(self::EQUIPMENT, 'New display name', 'EXT-01', 'operational', self::FACILITY);
    });
    $this->sites->expects(self::once())->method('find')->with(self::ORGANIZATION, self::SITE, self::FACILITY)->willReturn(new ServiceRequestSiteTarget(self::SITE, 'Renamed warehouse', false, ['id' => self::OTHER_REQUEST, 'name' => 'Current owner']));
  }

  private function noTargetReads(): void
  {
    $this->sites->expects(self::never())->method('lock');
    $this->sites->expects(self::never())->method('find');
    $this->equipment->expects(self::never())->method('find');
  }

  private function noEffects(): void
  {
    $this->work->expects(self::never())->method('reserveSelection');
    $this->work->expects(self::never())->method('createOrLink');
    $this->requests->expects(self::never())->method('save');
    $this->requests->expects(self::never())->method('saveConversionReceipt');
    $this->events->expects(self::never())->method('dispatch');
    $this->clock->expects(self::never())->method('now');
  }

  private function handler(): ConvertServiceRequestHandler
  {
    return new ConvertServiceRequestHandler($this->requests, new ServiceRequestAccessGuard($this->authorization), new ServiceRequestTargetGuard($this->equipment, $this->sites), $this->work, $this->clock, $this->transactions, $this->events);
  }

  private static function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T12:00:00Z');
  }

  private static function requested(): ServiceRequest
  {
    return ServiceRequest::create(self::REQUEST, self::ORGANIZATION, new ServiceRequestTarget(self::EQUIPMENT, self::SITE, ['equipment' => ['id' => self::EQUIPMENT, 'name' => 'Extinguisher', 'assetCode' => 'EXT-01', 'status' => 'operational'], 'site' => ['id' => self::SITE, 'name' => 'Warehouse'], 'customer' => ['id' => self::OTHER_REQUEST, 'name' => 'Building owner']], null, null), new ServiceRequestContent('Repair extinguisher', 'Pressure gauge damaged', 'normal'), self::now());
  }

  private static function qualified(): ServiceRequest
  {
    return self::requested()->qualify('Repair approved', self::now()->modify('+1 hour'));
  }

  private static function command(?int $revision = 2, ?string $existingInterventionId = null, ?string $existingTaskId = null): ConvertServiceRequestCommand
  {
    return new ConvertServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, $revision, strtoupper(self::OPERATION), $existingInterventionId, $existingTaskId);
  }

  private static function fingerprint(?string $existingInterventionId, ?string $existingTaskId): string
  {
    return hash('sha256', json_encode(['requestId' => self::REQUEST, 'existingInterventionId' => $existingInterventionId, 'existingTaskId' => $existingTaskId], JSON_THROW_ON_ERROR));
  }

  private static function receipt(string $hash, string $requestId = self::REQUEST): ServiceRequestConversionReceipt
  {
    return new ServiceRequestConversionReceipt(self::ORGANIZATION, $requestId, self::OPERATION, $hash, self::INTERVENTION, self::TASK, self::now()->modify('+2 hours'));
  }

  /**
   * @param callable():mixed $operation
   */
  private static function assertReason(string $reason, callable $operation): void
  {
    try {
      $operation();
      self::fail('Expected a service request refusal.');
    } catch (ServiceRequestException $exception) {
      self::assertSame($reason, $exception->reason);
    }
  }
  // #endregion
}
