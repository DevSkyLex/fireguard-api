<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Contract\Event\EquipmentChangedEvent;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Maintenance\Application\Service\MaintenanceScheduleService;
use Maintenance\Infrastructure\Persistence\Doctrine\Lock\MaintenanceScheduleLockAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenanceScheduleRepository;
use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, OutboxEvent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function array_filter;

/** Source changes, stale deliveries and shared writer exclusion against PostgreSQL. */
final class MaintenanceRecalculationContractTest extends KernelTestCase
{
  private const string ORG = 'c5000000-0000-4000-8000-000000000001';

  private const string EQUIPMENT = 'c5000000-0000-4000-8000-000000000002';

  private EntityManagerInterface $em;

  private MaintenanceScheduleRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $repository = self::getContainer()->get(MaintenanceScheduleRepository::class);
    self::assertInstanceOf(MaintenanceScheduleRepository::class, $repository);
    $this->repository = $repository;
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Maintenance recalculation';
    $org->slug = 'maintenance-recalculation-contract';
    $org->ownerUserId = self::EQUIPMENT;
    $org->createdByUserId = self::EQUIPMENT;
    $org->status = 'active';
    $org->isActive = true;
    $org->settings = ['compliance' => ['inspection_periodicity_defaults' => ['fire_extinguisher' => 'P90D']]];
    $org->createdAt = new DateTimeImmutable();
    $org->updatedAt = $org->createdAt;
    $this->em->persist($org);
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $org;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'in_stock';
    $equipment->createdAt = new DateTimeImmutable();
    $equipment->updatedAt = $equipment->createdAt;
    $this->em->persist($equipment);
    $this->em->flush();
  }

  #[Test]
  public function publishedEquipmentEnqueuesRecalculationAndDelayedEventsReadCurrentState(): void
  {
    $transport = self::getContainer()->get('messenger.transport.main_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $transport);
    $changes = array_filter($transport->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof OutboxEvent && $envelope->getMessage()->event instanceof EquipmentChangedEvent);
    self::assertNotEmpty($changes);
    $service = self::getContainer()->get(MaintenanceScheduleService::class);
    self::assertInstanceOf(MaintenanceScheduleService::class, $service);
    $closedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $service->onInspectionClosed(self::ORG, self::EQUIPMENT, $closedAt);
    self::assertSame('2026-04-01', $this->repository->findByOrganizationAndEquipment(self::ORG, self::EQUIPMENT)?->nextDueAt?->format('Y-m-d'));
    $org = $this->em->find(OrganizationRecord::class, self::ORG);
    self::assertNotNull($org);
    $org->settings = ['compliance' => ['inspection_periodicity_defaults' => ['fire_extinguisher' => 'P180D']]];
    $equipment = $this->em->find(EquipmentRecord::class, self::EQUIPMENT);
    self::assertNotNull($equipment);
    $equipment->facilityId = 'c5000000-0000-4000-8000-000000000003';
    $this->em->flush();
    $deliver = self::getContainer()->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $deliver);
    $deliver(new OutboxEvent('policy-change', new OrganizationSettingsUpdatedEvent(self::ORG, ['compliance'])));
    $deliver(new OutboxEvent('equipment-change', new EquipmentChangedEvent(self::ORG, self::EQUIPMENT)));
    // Duplicate old closure cannot revert the latest closed date or policy.
    $service->onInspectionClosed(self::ORG, self::EQUIPMENT, $closedAt->modify('-30 days'));
    $schedule = $this->repository->findByOrganizationAndEquipment(self::ORG, self::EQUIPMENT);
    self::assertNotNull($schedule);
    self::assertSame('2026-06-30', $schedule->nextDueAt?->format('Y-m-d'));
    self::assertEquals($closedAt, $schedule->lastInspectionClosedAt);
    self::assertSame($equipment->facilityId, $schedule->facilityId);
    self::assertNotNull($schedule->evaluatedAt);
    $equipment->status = 'decommissioned';
    $this->em->flush();
    $deliver(new OutboxEvent('equipment-change', new EquipmentChangedEvent(self::ORG, self::EQUIPMENT)));
    self::assertNull($this->repository->findByOrganizationAndEquipment(self::ORG, self::EQUIPMENT));
  }

  #[Test]
  public function refreshRecoversMissedInspectionSynchronizationAndIgnoresDrafts(): void
  {
    foreach (['published', 'draft'] as $index => $recordStatus) {
      $inspection = new \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord();
      $inspection->id = 'c5000000-0000-4000-8000-00000000001' . $index;
      $inspection->organization = $this->em->getReference(OrganizationRecord::class, self::ORG);
      $inspection->equipmentId = self::EQUIPMENT;
      $inspection->recordStatus = $recordStatus;
      $inspection->inspectorType = 'internal';
      $inspection->inspectorName = 'Contract test';
      $inspection->status = 'closed';
      $inspection->result = 'pass';
      $inspection->performedAt = new DateTimeImmutable(0 === $index ? '2026-01-01' : '2026-02-01');
      $inspection->createdAt = $inspection->performedAt;
      $inspection->updatedAt = $inspection->performedAt;
      $this->em->persist($inspection);
    }
    $this->em->flush();
    $service = self::getContainer()->get(MaintenanceScheduleService::class);
    self::assertInstanceOf(MaintenanceScheduleService::class, $service);
    $service->refreshEquipment(self::ORG, self::EQUIPMENT);
    $schedule = $this->repository->findByOrganizationAndEquipment(self::ORG, self::EQUIPMENT);
    self::assertNotNull($schedule);
    self::assertSame('2026-01-01', $schedule->lastInspectionClosedAt?->format('Y-m-d'));
    self::assertSame('2026-04-01', $schedule->nextDueAt?->format('Y-m-d'));
  }

  #[Test]
  public function scheduleLockExcludesAnotherWorkerEvenBeforeFirstInsert(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $first = DriverManager::getConnection(['url' => $url]);
    $second = DriverManager::getConnection(['url' => $url]);

    try {
      $firstLock = new MaintenanceScheduleLockAdapter($first);
      $secondLock = new MaintenanceScheduleLockAdapter($second);
      $second->executeStatement("SET lock_timeout = '75ms'");
      $firstLock->synchronized(self::ORG, self::EQUIPMENT, function () use ($secondLock): void {
        try {
          $secondLock->synchronized(self::ORG, self::EQUIPMENT, static fn (): bool => true);
          self::fail('A second writer entered the same schedule transaction.');
        } catch (DriverException $exception) {
          self::assertSame('55P03', $exception->getSQLState());
        }
      });
      $calls = 0;
      $secondLock->synchronized(self::ORG, self::EQUIPMENT, static function () use (&$calls): void { ++$calls; });
      self::assertSame(1, $calls);
    } finally {
      $first->close();
      $second->close();
    }
  }
}
