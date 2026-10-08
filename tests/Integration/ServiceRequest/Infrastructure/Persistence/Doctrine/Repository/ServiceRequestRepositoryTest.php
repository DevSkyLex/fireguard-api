<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceRequest\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use ServiceRequest\Domain\ValueObject\ServiceRequestConversionReceipt;
use ServiceRequest\Infrastructure\Persistence\Doctrine\Repository\ServiceRequestRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function count;
use function str_repeat;

use const DATE_ATOM;

/**
 * Class ServiceRequestRepositoryTest
 *
 * Exercises retained maintenance requests and conversion receipts on PostgreSQL main.
 *
 * @category Integration Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ServiceRequestRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a0e10000-0000-4000-8000-000000000001';

  private const string OTHER_ORGANIZATION_ID = 'a0e10000-0000-4000-8000-000000000002';

  private const string REQUEST_ID = 'a0e10000-0000-4000-8000-000000000003';

  private const string SECOND_REQUEST_ID = 'a0e10000-0000-4000-8000-000000000004';

  private const string EQUIPMENT_ID = 'a0e10000-0000-4000-8000-000000000005';

  private const string SECOND_EQUIPMENT_ID = 'a0e10000-0000-4000-8000-000000000006';

  private const string SITE_ID = 'a0e10000-0000-4000-8000-000000000007';

  private const string SECOND_SITE_ID = 'a0e10000-0000-4000-8000-000000000008';

  private const string INSPECTION_ID = 'a0e10000-0000-4000-8000-000000000009';

  private const string NON_CONFORMITY_ID = 'a0e10000-0000-4000-8000-000000000010';

  private const string INTERVENTION_ID = 'a0e10000-0000-4000-8000-000000000011';

  private const string TASK_ID = 'a0e10000-0000-4000-8000-000000000012';

  private const string OPERATION_ID = 'a0e10000-0000-4000-8000-000000000013';

  private const string SECOND_OPERATION_ID = 'a0e10000-0000-4000-8000-000000000014';

  private EntityManagerInterface $entityManager;

  private Connection $connection;

  private ServiceRequestRepository $repository;

  // #region Methods
  protected function setUp(): void
  {
    self::bootKernel();
    $entityManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
    $this->entityManager = $entityManager;
    $this->connection = $entityManager->getConnection();
    $this->repository = new ServiceRequestRepository($entityManager);
    foreach ([self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID] as $organizationId) {
      $this->connection->delete('service_request_conversion_receipts', ['organization_id' => $organizationId]);
      $this->connection->delete('service_requests', ['organization_id' => $organizationId]);
    }
  }

  #[Test]
  public function retainsTheFullConvertedRequestAndCustomerIdentityAfterReload(): void
  {
    $request = self::request()->qualify('Gauge replacement approved', self::now()->modify('+1 hour'))->convert(self::INTERVENTION_ID, self::TASK_ID, self::now()->modify('+2 hours'));
    $this->repository->save($request);
    $this->entityManager->clear();
    $stored = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);

    self::assertSame($request->id, $stored->id);
    self::assertSame($request->organizationId, $stored->organizationId);
    self::assertSame($request->equipmentId, $stored->equipmentId);
    self::assertSame($request->siteId, $stored->siteId);
    self::assertEquals($request->targetSnapshot, $stored->targetSnapshot);
    self::assertSame(['id' => self::SECOND_OPERATION_ID, 'name' => 'Building owner'], $stored->targetSnapshot['customer']);
    self::assertSame('Repair extinguisher', $stored->title);
    self::assertSame('Pressure gauge damaged', $stored->description);
    self::assertSame('high', $stored->priority);
    self::assertSame(self::INSPECTION_ID, $stored->originInspectionId);
    self::assertSame(self::NON_CONFORMITY_ID, $stored->originNonConformityId);
    self::assertSame('converted', $stored->status);
    self::assertSame(3, $stored->revision);
    self::assertSame('Gauge replacement approved', $stored->qualificationNote);
    self::assertSame(self::INTERVENTION_ID, $stored->interventionId);
    self::assertSame(self::TASK_ID, $stored->taskId);
    self::assertNull($stored->decisionReason);
    self::assertNull($stored->rejectedAt);
    self::assertNull($stored->cancelledAt);
    self::assertSame('2026-10-06T12:00:00+00:00', $stored->requestedAt->format(DATE_ATOM));
    self::assertSame('2026-10-06T13:00:00+00:00', $stored->qualifiedAt?->format(DATE_ATOM));
    self::assertSame('2026-10-06T14:00:00+00:00', $stored->convertedAt?->format(DATE_ATOM));
    self::assertSame('2026-10-06T14:00:00+00:00', $stored->updatedAt->format(DATE_ATOM));
    self::assertSame('2026-10-06 12:00:00', $this->connection->fetchOne('SELECT requested_at FROM service_requests WHERE id = ?', [self::REQUEST_ID]));
    self::assertSame('2026-10-06 14:00:00', $this->connection->fetchOne('SELECT converted_at FROM service_requests WHERE id = ?', [self::REQUEST_ID]));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function decisionStates(): iterable
  {
    yield 'rejected' => ['rejected'];
    yield 'cancelled' => ['cancelled'];
  }

  #[Test]
  #[DataProvider('decisionStates')]
  public function retainsDecisionReasonsAndTheirActualUtcDates(string $state): void
  {
    $request = self::request()->qualify('Qualified from inspection', self::now()->modify('+1 hour'));
    $decidedAt = self::now()->modify('+2 hours');
    $request = 'rejected' === $state ? $request->reject('Not in contract', $decidedAt) : $request->cancel('Duplicate work', $decidedAt);
    $this->repository->save($request);
    $this->entityManager->clear();
    $stored = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);

    self::assertSame($state, $stored->status);
    self::assertSame($request->decisionReason, $stored->decisionReason);
    self::assertSame('Qualified from inspection', $stored->qualificationNote);
    self::assertSame('2026-10-06T13:00:00+00:00', $stored->qualifiedAt?->format(DATE_ATOM));
    self::assertSame('2026-10-06T14:00:00+00:00', ('rejected' === $state ? $stored->rejectedAt : $stored->cancelledAt)?->format(DATE_ATOM));
    self::assertNull('rejected' === $state ? $stored->cancelledAt : $stored->rejectedAt);
    self::assertNull($stored->convertedAt);
    self::assertNull($stored->interventionId);
    self::assertNull($stored->taskId);
    self::assertSame(3, $stored->revision);
  }

  #[Test]
  public function persistsTheExplicitEquipmentSelectionForASiteOnlyRequest(): void
  {
    $request = ServiceRequest::create(self::REQUEST_ID, self::ORGANIZATION_ID, new ServiceRequestTarget(null, self::SITE_ID, ['site' => ['id' => self::SITE_ID, 'name' => 'Warehouse']], null, null), new ServiceRequestContent('Locate leak', 'Equipment to identify', 'normal'), self::now());
    $this->repository->save($request);
    $this->entityManager->clear();
    $loaded = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($loaded);
    self::assertNull($loaded->equipmentId);
    self::assertSame(self::SITE_ID, $loaded->siteId);
    self::assertNull($loaded->originInspectionId);
    self::assertNull($loaded->originNonConformityId);
    $snapshot = ['equipment' => ['id' => self::EQUIPMENT_ID, 'name' => 'Valve'], 'site' => ['id' => self::SITE_ID, 'name' => 'Warehouse']];
    $assigned = $loaded->assignEquipment(self::EQUIPMENT_ID, self::SITE_ID, $snapshot, self::now()->modify('+1 hour'));
    $this->repository->save($assigned, $loaded->revision);
    $this->entityManager->clear();
    $stored = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);

    self::assertSame(self::EQUIPMENT_ID, $stored->equipmentId);
    self::assertSame(self::SITE_ID, $stored->siteId);
    self::assertEquals($snapshot, $stored->targetSnapshot);
    self::assertSame('requested', $stored->status);
    self::assertSame(2, $stored->revision);
    self::assertSame('2026-10-06T13:00:00+00:00', $stored->updatedAt->format(DATE_ATOM));
    self::assertNull($stored->qualifiedAt);
  }

  #[Test]
  public function refusesStaleWritesWithoutChangingTheWinningRevision(): void
  {
    $request = self::request();
    $this->repository->save($request);
    $first = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    $second = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($first);
    self::assertNotNull($second);
    $winner = $first->change(['title' => 'Replace gauge'], self::now()->modify('+1 hour'));
    $this->repository->save($winner, 1);

    try {
      $this->repository->save($second->change(['priority' => 'urgent'], self::now()->modify('+2 hours')), 1);
      self::fail('A stale revision must not replace the committed request.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_revision_stale', $exception->reason);
    }
    $this->entityManager->clear();
    $stored = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);
    self::assertSame('Replace gauge', $stored->title);
    self::assertSame('high', $stored->priority);
    self::assertSame(2, $stored->revision);
  }

  #[Test]
  public function scopesReadsAndUpdatesToTheOwningOrganization(): void
  {
    $request = self::request();
    $this->repository->save($request);
    self::assertNull($this->repository->find(self::REQUEST_ID, self::OTHER_ORGANIZATION_ID));
    $foreign = self::request(organizationId: self::OTHER_ORGANIZATION_ID)->change(['title' => 'Foreign overwrite'], self::now()->modify('+1 hour'));

    try {
      $this->repository->save($foreign, 1);
      self::fail('The foreign organization must not mutate the request.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_revision_stale', $exception->reason);
    }
    $this->entityManager->clear();
    $stored = $this->repository->find(self::REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);
    self::assertSame('Repair extinguisher', $stored->title);
    self::assertSame(1, $stored->revision);
    self::assertSame(0, $this->repository->count(self::OTHER_ORGANIZATION_ID, null, null, null, ''));
  }

  #[Test]
  public function usesTheSameFiltersForListsAndCountsAndPaginatesWithoutDuplicates(): void
  {
    $first = self::request(title: 'Valve leak', description: 'Pressure lost');
    $qualified = self::request(self::SECOND_REQUEST_ID, title: 'Gauge repair', description: 'Control defect')->qualify(null, self::now()->modify('+1 hour'));
    $rejected = self::request(self::INSPECTION_ID, siteId: self::SECOND_SITE_ID, title: 'Old valve', description: 'Leak resolved')->reject('Outside scope', self::now());
    $cancelled = self::request(self::NON_CONFORMITY_ID, equipmentId: self::SECOND_EQUIPMENT_ID, siteId: self::SECOND_SITE_ID, title: 'Detector test')->cancel('Duplicate', self::now());
    $converted = self::request(self::INTERVENTION_ID, equipmentId: self::SECOND_EQUIPMENT_ID, siteId: self::SECOND_SITE_ID, title: 'Valve replacement')->qualify(null, self::now())->convert(self::INTERVENTION_ID, self::TASK_ID, self::now());
    $foreign = self::request(self::TASK_ID, self::OTHER_ORGANIZATION_ID, title: 'Valve leak', description: 'Control defect');
    foreach ([$first, $qualified, $rejected, $cancelled, $converted, $foreign] as $request) {
      $this->repository->save($request);
    }
    $all = $this->repository->list(self::ORGANIZATION_ID, null, null, null, '', 0, 10);
    self::assertCount(5, $all);
    self::assertSame([self::REQUEST_ID, self::SECOND_REQUEST_ID, self::INSPECTION_ID, self::NON_CONFORMITY_ID, self::INTERVENTION_ID], self::ids($all));
    self::assertSame(5, $this->repository->count(self::ORGANIZATION_ID, null, null, null, ''));
    $firstPage = $this->repository->list(self::ORGANIZATION_ID, null, null, null, '', 0, 2);
    $secondPage = $this->repository->list(self::ORGANIZATION_ID, null, null, null, '', 2, 3);
    self::assertSame(self::ids($all), [...self::ids($firstPage), ...self::ids($secondPage)]);
    self::assertSame([], $this->repository->list(self::ORGANIZATION_ID, null, null, null, '', 5, 3));

    foreach ([
      ['qualified', self::EQUIPMENT_ID, self::SITE_ID, 'CONTROL', [self::SECOND_REQUEST_ID]],
      [null, self::EQUIPMENT_ID, null, '', [self::REQUEST_ID, self::SECOND_REQUEST_ID, self::INSPECTION_ID]],
      [null, null, self::SECOND_SITE_ID, 'valve', [self::INSPECTION_ID, self::INTERVENTION_ID]],
      [null, null, null, "' OR 1=1 --", []],
      [null, null, null, '%', []],
      [null, null, null, '_', []],
      [null, null, null, '\\', []],
    ] as [$status, $equipmentId, $siteId, $search, $expectedIds]) {
      $matches = $this->repository->list(self::ORGANIZATION_ID, $status, $equipmentId, $siteId, $search, 0, 10);
      self::assertEqualsCanonicalizing($expectedIds, self::ids($matches));
      self::assertSame(count($matches), $this->repository->count(self::ORGANIZATION_ID, $status, $equipmentId, $siteId, $search));
    }
  }

  #[Test]
  public function retainsAndScopesConversionReceiptsByRequestAndOperation(): void
  {
    $request = self::convertedRequest();
    $this->repository->save($request);
    $receipt = self::receipt();
    $this->repository->saveConversionReceipt($receipt);
    $foreignRequest = self::convertedRequest(self::SECOND_REQUEST_ID, self::OTHER_ORGANIZATION_ID);
    $this->repository->save($foreignRequest);
    $foreignReceipt = self::receipt(self::SECOND_REQUEST_ID, self::OTHER_ORGANIZATION_ID);
    $this->repository->saveConversionReceipt($foreignReceipt);
    $this->entityManager->clear();

    $byRequest = $this->repository->conversionReceiptForRequest(self::REQUEST_ID, self::ORGANIZATION_ID);
    $byOperation = $this->repository->conversionReceiptByOperation(self::ORGANIZATION_ID, self::OPERATION_ID);
    self::assertNotNull($byRequest);
    self::assertNotNull($byOperation);
    self::assertSame(self::ORGANIZATION_ID, $byRequest->organizationId);
    self::assertSame(self::REQUEST_ID, $byRequest->requestId);
    self::assertSame(self::OPERATION_ID, $byRequest->clientOperationId);
    self::assertSame(str_repeat('a', 64), $byRequest->payloadHash);
    self::assertSame(self::INTERVENTION_ID, $byRequest->interventionId);
    self::assertSame(self::TASK_ID, $byRequest->taskId);
    self::assertSame('2026-10-06T14:00:00+00:00', $byRequest->createdAt->format(DATE_ATOM));
    self::assertEquals($byRequest, $byOperation);
    self::assertNull($this->repository->conversionReceiptForRequest(self::REQUEST_ID, self::OTHER_ORGANIZATION_ID));
    self::assertSame(self::SECOND_REQUEST_ID, $this->repository->conversionReceiptByOperation(self::OTHER_ORGANIZATION_ID, self::OPERATION_ID)?->requestId);
    self::assertNull($this->repository->conversionReceiptByOperation(self::ORGANIZATION_ID, self::SECOND_OPERATION_ID));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function invalidReceiptOwners(): iterable
  {
    yield 'missing request' => ['missing'];
    yield 'requested' => ['requested'];
    yield 'foreign organization' => ['foreign'];
    yield 'different intervention' => ['intervention'];
    yield 'different task' => ['task'];
  }

  #[Test]
  #[DataProvider('invalidReceiptOwners')]
  public function refusesReceiptsThatDoNotMatchAConvertedRequest(string $case): void
  {
    if ('missing' !== $case) {
      $this->repository->save('requested' === $case ? self::request() : self::convertedRequest());
    }
    $receipt = self::receipt(
      organizationId: 'foreign' === $case ? self::OTHER_ORGANIZATION_ID : self::ORGANIZATION_ID,
      interventionId: 'intervention' === $case ? self::SECOND_EQUIPMENT_ID : self::INTERVENTION_ID,
      taskId: 'task' === $case ? self::SECOND_EQUIPMENT_ID : self::TASK_ID,
    );

    try {
      $this->repository->saveConversionReceipt($receipt);
      self::fail('A receipt must identify the committed converted request.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_operation_conflict', $exception->reason);
    }
    self::assertNull($this->repository->conversionReceiptForRequest(self::REQUEST_ID, self::ORGANIZATION_ID));
  }

  #[Test]
  public function refusesASecondReceiptForTheSameRequest(): void
  {
    $this->repository->save(self::convertedRequest());
    $this->repository->saveConversionReceipt(self::receipt());

    try {
      $this->connection->transactional(function (): void {
        $this->repository->saveConversionReceipt(self::receipt(operationId: self::SECOND_OPERATION_ID));
      });
      self::fail('One request must retain a single conversion receipt.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_operation_conflict', $exception->reason);
    }
    self::assertSame(self::OPERATION_ID, $this->repository->conversionReceiptForRequest(self::REQUEST_ID, self::ORGANIZATION_ID)?->clientOperationId);
    self::assertNull($this->repository->conversionReceiptByOperation(self::ORGANIZATION_ID, self::SECOND_OPERATION_ID));
  }

  #[Test]
  public function rollsBackTheRequestConversionWhenAnOperationReceiptConflicts(): void
  {
    $this->repository->save(self::convertedRequest());
    $this->repository->saveConversionReceipt(self::receipt());
    $qualified = self::request(self::SECOND_REQUEST_ID)->qualify('Repair approved', self::now()->modify('+1 hour'));
    $this->repository->save($qualified);

    try {
      $this->connection->transactional(function (): void {
        $request = $this->repository->find(self::SECOND_REQUEST_ID, self::ORGANIZATION_ID, true);
        self::assertNotNull($request);
        $this->repository->save($request->convert(self::INTERVENTION_ID, self::TASK_ID, self::now()->modify('+2 hours')), $request->revision);
        $this->repository->saveConversionReceipt(self::receipt(self::SECOND_REQUEST_ID));
      });
      self::fail('An operation receipt cannot convert two different requests.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_operation_conflict', $exception->reason);
    }
    $this->entityManager->clear();
    $stored = $this->repository->find(self::SECOND_REQUEST_ID, self::ORGANIZATION_ID);
    self::assertNotNull($stored);
    self::assertSame('qualified', $stored->status);
    self::assertSame(2, $stored->revision);
    self::assertNull($stored->convertedAt);
    self::assertNull($stored->interventionId);
    self::assertNull($stored->taskId);
    self::assertNull($this->repository->conversionReceiptForRequest(self::SECOND_REQUEST_ID, self::ORGANIZATION_ID));
    self::assertSame(self::REQUEST_ID, $this->repository->conversionReceiptByOperation(self::ORGANIZATION_ID, self::OPERATION_ID)?->requestId);
  }

  private static function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T14:00:00+02:00');
  }

  private static function request(string $id = self::REQUEST_ID, string $organizationId = self::ORGANIZATION_ID, string $equipmentId = self::EQUIPMENT_ID, string $siteId = self::SITE_ID, string $title = 'Repair extinguisher', string $description = 'Pressure gauge damaged'): ServiceRequest
  {
    return ServiceRequest::create($id, $organizationId, new ServiceRequestTarget($equipmentId, $siteId, ['equipment' => ['id' => $equipmentId, 'name' => 'Extinguisher', 'assetCode' => 'EXT-01'], 'site' => ['id' => $siteId, 'name' => 'Warehouse'], 'customer' => ['id' => self::SECOND_OPERATION_ID, 'name' => 'Building owner']], self::INSPECTION_ID, self::NON_CONFORMITY_ID), new ServiceRequestContent($title, $description, 'high'), self::now());
  }

  private static function convertedRequest(string $id = self::REQUEST_ID, string $organizationId = self::ORGANIZATION_ID): ServiceRequest
  {
    return self::request($id, $organizationId)->qualify('Repair approved', self::now()->modify('+1 hour'))->convert(self::INTERVENTION_ID, self::TASK_ID, self::now()->modify('+2 hours'));
  }

  private static function receipt(string $requestId = self::REQUEST_ID, string $organizationId = self::ORGANIZATION_ID, string $operationId = self::OPERATION_ID, string $interventionId = self::INTERVENTION_ID, string $taskId = self::TASK_ID): ServiceRequestConversionReceipt
  {
    return new ServiceRequestConversionReceipt($organizationId, $requestId, $operationId, str_repeat('a', 64), $interventionId, $taskId, self::now()->modify('+2 hours'));
  }

  /**
   * @param list<ServiceRequest> $requests
   *
   * @return list<string>
   */
  private static function ids(array $requests): array
  {
    return array_map(static fn (ServiceRequest $request): string => $request->id, $requests);
  }
  // #endregion
}
