<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Equipment\InterventionServiceReportAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InterventionServiceReportAdapterTest.
 *
 * Exercises the real DQL query + resource-pattern filtering behind
 * `serviceReport()`, hard to trust from a mock: only applied changes
 * targeting a single-segment `/api/equipment/{id}` resource must surface,
 * with the work-item action taking priority over the patch-derived
 * fallback.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionServiceReportAdapter::class)]
final class InterventionServiceReportAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655460001';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655460002';

  private const string RESPONSIBLE_ID = '660e8400-e29b-41d4-a716-446655460003';

  private const string EQUIPMENT_ID_1 = '660e8400-e29b-41d4-a716-446655460010';

  private const string EQUIPMENT_ID_2 = '660e8400-e29b-41d4-a716-446655460011';

  private const string EQUIPMENT_ID_3 = '660e8400-e29b-41d4-a716-446655460012';

  private EntityManagerInterface $entityManager;

  private InterventionServiceReportAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new InterventionServiceReportAdapter($this->entityManager);

    $this->createOrganization();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testServiceReportReturnsNullWhenInterventionIsNotFound(): void
  {
    self::assertNull($this->adapter->serviceReport('660e8400-e29b-41d4-a716-446655469999'));
  }

  #[Test]
  public function testServiceReportReturnsOnlyAppliedEquipmentChanges(): void
  {
    $intervention = $this->createIntervention();

    $workItem = new InterventionWorkItemRecord();
    $workItem->id = '660e8400-e29b-41d4-a716-446655460020';
    $workItem->intervention = $intervention;
    $workItem->action = 'inventory';
    $workItem->source = 'planned';
    $workItem->status = 'completed';
    $workItem->required = true;
    $workItem->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $workItem->updatedAt = $workItem->createdAt;
    $this->entityManager->persist($workItem);

    // Applied, no linked work item: action falls back to the patch-derived label.
    $this->createChange('660e8400-e29b-41d4-a716-446655460030', $intervention, '/api/equipment/' . self::EQUIPMENT_ID_1, ['status' => 'operational'], 'applied');

    // Applied, linked work item: action comes from the work item, not the patch.
    $this->createChange('660e8400-e29b-41d4-a716-446655460031', $intervention, '/api/equipment/' . self::EQUIPMENT_ID_2, ['brand' => 'Acme'], 'applied', $workItem);

    // Applied but not an equipment resource: must be skipped.
    $this->createChange('660e8400-e29b-41d4-a716-446655460032', $intervention, '/api/facilities/660e8400-e29b-41d4-a716-446655460099', ['status' => 'active'], 'applied');

    // Equipment resource but still proposed (not applied): must be skipped.
    $this->createChange('660e8400-e29b-41d4-a716-446655460033', $intervention, '/api/equipment/' . self::EQUIPMENT_ID_3, ['status' => 'operational'], 'proposed');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $report = $this->adapter->serviceReport(self::INTERVENTION_ID);

    self::assertNotNull($report);
    self::assertSame(7, $report->number);
    self::assertSame(self::RESPONSIBLE_ID, $report->actorId);
    self::assertCount(2, $report->equipment);

    $byEquipmentId = [];
    foreach ($report->equipment as $entry) {
      $byEquipmentId[$entry->equipmentId] = $entry;
    }

    self::assertArrayHasKey(self::EQUIPMENT_ID_1, $byEquipmentId);
    self::assertSame('status_change', $byEquipmentId[self::EQUIPMENT_ID_1]->action);
    self::assertSame('660e8400-e29b-41d4-a716-446655460030', $byEquipmentId[self::EQUIPMENT_ID_1]->changeToken);
    self::assertNull($byEquipmentId[self::EQUIPMENT_ID_1]->workItemId);

    self::assertArrayHasKey(self::EQUIPMENT_ID_2, $byEquipmentId);
    self::assertSame('inventory', $byEquipmentId[self::EQUIPMENT_ID_2]->action);
    self::assertSame('660e8400-e29b-41d4-a716-446655460031', $byEquipmentId[self::EQUIPMENT_ID_2]->changeToken);
    self::assertSame('660e8400-e29b-41d4-a716-446655460020', $byEquipmentId[self::EQUIPMENT_ID_2]->workItemId);

    self::assertArrayNotHasKey(self::EQUIPMENT_ID_3, $byEquipmentId);
  }

  private function createIntervention(): InterventionRecord
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Service report adapter test';
    $intervention->number = 7;
    $intervention->status = 'published';
    $intervention->responsibleId = self::RESPONSIBLE_ID;
    $intervention->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $intervention->updatedAt = $intervention->createdAt;
    $this->entityManager->persist($intervention);

    return $intervention;
  }

  /**
   * @param array<string, mixed> $patch
   */
  private function createChange(
    string $id,
    InterventionRecord $intervention,
    string $resource,
    array $patch,
    string $status,
    ?InterventionWorkItemRecord $workItem = null,
  ): void {
    $change = new InterventionChangeRecord();
    $change->id = $id;
    $change->intervention = $intervention;
    $change->workItem = $workItem;
    $change->resource = $resource;
    $change->patch = $patch;
    $change->status = $status;
    $change->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $change->updatedAt = $change->createdAt;
    $this->entityManager->persist($change);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention Service Report Adapter Test';
    $organization->slug = 'intervention-service-report-adapter-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_changes WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_work_items WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
