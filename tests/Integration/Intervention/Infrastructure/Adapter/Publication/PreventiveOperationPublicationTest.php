<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Publication;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Infrastructure\Adapter\Publication\DoctrinePublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanState};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_keys;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class PreventiveOperationPublicationTest
 *
 * Proves that real publication and occurrence acknowledgement share the main transaction.
 *
 * @category Integration Tests
 */
final class PreventiveOperationPublicationTest extends KernelTestCase
{
  private const string ORG = '880e8400-e29b-41d4-a716-446655448001';

  private const string EQUIPMENT = '880e8400-e29b-41d4-a716-446655448002';

  private const string ORDER = '880e8400-e29b-41d4-a716-446655448003';

  private const string TASK = '880e8400-e29b-41d4-a716-446655448004';

  private const string PLAN = '880e8400-e29b-41d4-a716-446655448005';

  private const string OTHER_PLAN = '880e8400-e29b-41d4-a716-446655448006';

  private const string OCCURRENCE = '880e8400-e29b-41d4-a716-446655448007';

  private const string PUBLICATION = '880e8400-e29b-41d4-a716-446655448008';

  public function testValidatedMaintenanceDoesNotAdvanceTheIndependentAnnualControl(): void
  {
    [$publication, $store] = $this->fixture('maintenance');
    self::assertTrue($publication->publish(self::PUBLICATION));
    self::assertSame('completed', $store->findOccurrence(self::ORG, self::OCCURRENCE)?->status);
    self::assertSame('2026-10-30', $this->nextDue($store, self::PLAN));
    self::assertSame('2026-09-30', $this->nextDue($store, self::OTHER_PLAN));
    $other = $store->find(self::ORG, self::OTHER_PLAN);
    self::assertInstanceOf(MaintenancePlanState::class, $other);
    self::assertNull($other->lastCompletedAt);
    self::assertTrue($store->hasReceipt(self::TASK, self::OCCURRENCE));
    self::assertFalse($publication->publish(self::PUBLICATION));
    self::assertSame('2026-10-30', $this->nextDue($store, self::PLAN));
  }

  public function testClosedAdverseControlIsPerformedWithoutAdvancingTheIndependentMaintenance(): void
  {
    [$publication, $store] = $this->fixture('control');
    self::assertTrue($publication->publish(self::PUBLICATION));
    self::assertSame('completed', $store->findOccurrence(self::ORG, self::OCCURRENCE)?->status);
    self::assertSame('2027-09-30', $store->find(self::ORG, self::PLAN)?->nextDueAt?->format('Y-m-d'));
    self::assertSame('2026-09-30', $store->find(self::ORG, self::OTHER_PLAN)?->nextDueAt?->format('Y-m-d'));
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertSame('fail', $manager->getConnection()->fetchOne('SELECT result FROM inspections WHERE id = ?', ['880e8400-e29b-41d4-a716-446655448011']));
  }

  public function testClosureCustomerContainsOnlyIdentityAndRemainsFrozen(): void
  {
    [$publication] = $this->fixture('maintenance');
    self::assertTrue($publication->publish(self::PUBLICATION));
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $captured = $manager->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]);
    self::assertIsString($captured);
    $snapshot = json_decode($captured, true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($snapshot);
    self::assertSame(2, $snapshot['version']);
    self::assertSame(self::PUBLICATION, $snapshot['publicationId']);
    $customer = $snapshot['customer'] ?? null;
    self::assertIsArray($customer);
    self::assertSame(['id', 'name'], array_keys($customer));
    self::assertSame('Initial customer', $customer['name']);
    $items = $snapshot['workItems'] ?? null;
    self::assertIsArray($items);
    $first = $items[0] ?? null;
    self::assertIsArray($first);
    $identity = $first['equipmentIdentity'] ?? null;
    self::assertIsArray($identity);
    self::assertSame('Initial fire extinguisher', $identity['name']);
    self::assertSame('ASSET-PUBLISHED-42', $identity['assetReference']);
    self::assertSame('Historic brand', $identity['brand']);
    self::assertSame('Historic model', $identity['model']);
    self::assertSame('HISTORIC-SERIAL-42', $identity['serialNumber']);
    $equipmentSite = $identity['site'] ?? null;
    $equipmentCustomer = $identity['customer'] ?? null;
    self::assertIsArray($equipmentSite);
    self::assertIsArray($equipmentCustomer);
    self::assertSame('Fire prevention site', $equipmentSite['name'] ?? null);
    self::assertSame(['id', 'name'], array_keys($equipmentCustomer));
    $manager->getConnection()->executeStatement('UPDATE customers SET name = ? WHERE id = ?', ['Changed customer', $customer['id']]);
    $manager->getConnection()->executeStatement('UPDATE equipment SET name = ?, asset_code = ?, status = ? WHERE id = ?', ['Renamed replacement-era asset', 'NEW-ASSET-42', 'decommissioned', self::EQUIPMENT]);
    $manager->getConnection()->executeStatement('UPDATE facilities SET name = ?, status = ? WHERE id = ?', ['Changed site', 'archived', $identity['facilityId']]);
    self::assertSame($captured, $manager->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]));
    /** @var InterventionPublicationFactsPort $facts */
    $facts = self::getContainer()->get(InterventionPublicationFactsPort::class);
    $published = $facts->published(self::ORG, self::ORDER);
    self::assertNotNull($published);
    self::assertTrue($published->identityComplete);
    self::assertSame('Initial fire extinguisher', $published->workItems[0]->equipmentIdentity?->name);
    self::assertSame('Initial customer', $published->workItems[0]->customer['name'] ?? null);
    self::assertSame('Fire prevention site', $published->workItems[0]->site['name'] ?? null);
  }

  public function testClosedControlWithoutPreventiveOccurrenceCapturesItsValidatedOwnerResult(): void
  {
    [$publication] = $this->fixture('control');
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $task = $manager->find(InterventionWorkItemRecord::class, self::TASK);
    self::assertInstanceOf(InterventionWorkItemRecord::class, $task);
    $task->operationId = $task->occurrenceId = $task->operationKind = null;
    $manager->flush();
    self::assertTrue($publication->publish(self::PUBLICATION));
    /** @var InterventionPublicationFactsPort $facts */
    $facts = self::getContainer()->get(InterventionPublicationFactsPort::class);
    $result = $facts->published(self::ORG, self::ORDER);
    self::assertNotNull($result);
    self::assertTrue($result->workItems[0]->validated);
    self::assertSame('fail', $result->workItems[0]->executionResult['inspectionResult'] ?? null);
    self::assertSame(self::EQUIPMENT, $result->workItems[0]->executionResult['equipmentId'] ?? null);
    self::assertSame('performed', $result->workItems[0]->executionResult['outcome'] ?? null);
  }

  /**
   * @return array{DoctrinePublicationAdapter,MaintenancePlanStorePort}
   */
  private function fixture(string $kind): array
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $created = new DateTimeImmutable('2026-09-01T00:00:00+00:00');
    $due = new DateTimeImmutable('2026-09-30T00:00:00+00:00');
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Preventive publication test';
    $organization->slug = 'preventive-publication-test';
    $organization->ownerUserId = $organization->createdByUserId = '880e8400-e29b-41d4-a716-446655448009';
    $organization->createdAt = $organization->updatedAt = $created;
    $organization->status = 'active';
    $organization->isActive = true;
    $manager->persist($organization);
    $customer = new CustomerRecord();
    $customer->id = '880e8400-e29b-41d4-a716-446655448012';
    $customer->organizationId = self::ORG;
    $customer->name = 'Initial customer';
    $customer->contacts = [['name' => 'Private contact', 'email' => 'private@example.test', 'phone' => null, 'role' => null]];
    $customer->createdAt = $customer->updatedAt = $created;
    $manager->persist($customer);
    $site = new FacilityRecord();
    $site->id = '880e8400-e29b-41d4-a716-446655448010';
    $site->organization = $organization;
    $site->name = 'Fire prevention site';
    $site->type = 'site';
    $site->status = 'active';
    $site->customerId = $customer->id;
    $site->createdAt = $site->updatedAt = $created;
    $manager->persist($site);
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $organization;
    $equipment->facilityId = $site->id;
    $equipment->type = 'fire_extinguisher';
    $equipment->name = 'Initial fire extinguisher';
    $equipment->assetCode = 'ASSET-PUBLISHED-42';
    $equipment->brand = 'Historic brand';
    $equipment->model = 'Historic model';
    $equipment->serialNumber = 'HISTORIC-SERIAL-42';
    $equipment->createdAt = $equipment->updatedAt = $created;
    $manager->persist($equipment);
    $order = new InterventionRecord();
    $order->id = self::ORDER;
    $order->organization = $organization;
    $order->siteId = $site->id;
    $order->type = 'control' === $kind ? 'inspection_campaign' : 'preventive_maintenance';
    $order->name = 'Real preventive publication';
    $order->status = 'submitted';
    $order->number = 42;
    $order->createdAt = $order->updatedAt = $created;
    $manager->persist($order);
    $task = new InterventionWorkItemRecord();
    $task->id = self::TASK;
    $task->intervention = $order;
    $task->action = 'control' === $kind ? 'inspection' : 'maintenance';
    $task->status = 'completed';
    $task->target = '/api/equipment/' . self::EQUIPMENT;
    $task->operationId = self::PLAN;
    $task->occurrenceId = self::OCCURRENCE;
    $task->operationKind = $kind;
    $task->createdAt = $task->updatedAt = $created;
    if ('control' === $kind) {
      $inspection = new InspectionRecord();
      $inspection->id = '880e8400-e29b-41d4-a716-446655448011';
      $inspection->organization = $organization;
      $inspection->equipmentId = self::EQUIPMENT;
      $inspection->facilityId = $site->id;
      $inspection->interventionId = self::ORDER;
      $inspection->recordStatus = 'draft';
      $inspection->result = 'fail';
      $inspection->status = 'closed';
      $inspection->inspectorType = 'user';
      $inspection->inspectorName = 'Technician';
      $inspection->inspectorUserId = $organization->ownerUserId;
      $inspection->performedAt = new DateTimeImmutable('2026-09-30T15:00:00+02:00');
      $inspection->createdAt = $inspection->updatedAt = $created;
      $manager->persist($inspection);
      $task->resultResource = '/api/inspections/' . $inspection->id;
    } else {
      $task->executionResult = ['equipmentId' => self::EQUIPMENT, 'performedAt' => '2026-09-30T15:00:00+02:00', 'outcome' => 'successful', 'workPerformed' => 'Extinguisher maintenance', 'authorId' => $organization->ownerUserId, 'state' => 'staged'];
    }
    $manager->persist($task);
    $manager->flush();
    /** @var MaintenancePlanStorePort $store */
    $store = self::getContainer()->get(MaintenancePlanStorePort::class);
    $plan = new MaintenancePlanState(self::PLAN, self::ORG, self::EQUIPMENT, $site->id, 'fire_extinguisher', 'Source operation', $kind, 'control' === $kind ? 'P1Y' : 'P1M', 'fixed', $due, $due, true, null, null, null, $created, $created);
    $other = new MaintenancePlanState(self::OTHER_PLAN, self::ORG, self::EQUIPMENT, $site->id, 'fire_extinguisher', 'Independent operation', 'control' === $kind ? 'maintenance' : 'control', 'control' === $kind ? 'P1M' : 'P1Y', 'fixed', $due, $due, true, null, null, null, $created, $created);
    $occurrence = new MaintenanceOccurrenceState(self::OCCURRENCE, self::PLAN, self::ORG, $due, 'open', 1, self::ORDER, null, null, $created, $created, 42);
    $store->synchronized(self::ORG, static function () use ($store, $created, $plan, $other, $occurrence): void {
      $store->activateEngine(self::ORG, $created);
      $store->save($plan);
      $store->save($other);
      $store->saveOccurrence($occurrence);
    });
    /** @var DoctrinePublicationAdapter $publication */
    $publication = self::getContainer()->get(DoctrinePublicationAdapter::class);
    $publication->createOrGetPending(self::PUBLICATION, self::ORDER, 1);
    $publication->markProcessing(self::PUBLICATION);

    return [$publication, $store];
  }

  private function nextDue(MaintenancePlanStorePort $store, string $planId): string
  {
    $plan = $store->find(self::ORG, $planId);
    self::assertInstanceOf(MaintenancePlanState::class, $plan);
    self::assertInstanceOf(DateTimeImmutable::class, $plan->nextDueAt);

    return $plan->nextDueAt->format('Y-m-d');
  }
}
