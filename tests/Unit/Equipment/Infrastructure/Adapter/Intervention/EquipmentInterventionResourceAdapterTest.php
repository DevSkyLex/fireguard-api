<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Intervention\Domain\Exception\InterventionConflictException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EquipmentInterventionResourceAdapter::class)]
final class EquipmentInterventionResourceAdapterTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testPublishDraftsStampsCommissionedAtOnOperationalDraft(): void
  {
    $record = $this->draftRecord('operational');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');

    $this->adapter($this->entityManagerFindingDrafts($record), $synchronizer)
      ->publishDrafts(self::INTERVENTION_ID);

    self::assertSame('published', $record->recordStatus);
    self::assertSame(2, $record->revision);
    self::assertInstanceOf(DateTimeImmutable::class, $record->commissionedAt);
  }

  #[Test]
  public function testPublishDraftsOpensMaintenanceLogForUnderMaintenanceDraft(): void
  {
    $record = $this->draftRecord('under_maintenance');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())
      ->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'in_stock', 'under_maintenance');

    $this->adapter($this->entityManagerFindingDrafts($record), $synchronizer)
      ->publishDrafts(self::INTERVENTION_ID);

    self::assertSame('published', $record->recordStatus);
    // Under-maintenance implies it has been in service, so the first-commissioning
    // date is stamped too.
    self::assertInstanceOf(DateTimeImmutable::class, $record->commissionedAt);
  }

  #[Test]
  public function testPublishDraftsLeavesInStockDraftUntouched(): void
  {
    $record = $this->draftRecord('in_stock');

    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');

    $this->adapter($this->entityManagerFindingDrafts($record), $synchronizer)
      ->publishDrafts(self::INTERVENTION_ID);

    self::assertSame('published', $record->recordStatus);
    self::assertNull($record->commissionedAt);
  }

  #[Test]
  public function testApplyRejectsClearingFacilityOfUnderMaintenanceEquipment(): void
  {
    $record = $this->draftRecord('under_maintenance');
    $record->recordStatus = 'published';
    $record->facilityId = '550e8400-e29b-41d4-a716-446655440005';

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('In-service equipment must be assigned to a facility.');

    $this->adapter($entityManager, $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class))
      ->apply(self::ORGANIZATION_ID, '/api/equipment/' . self::EQUIPMENT_ID, ['facility' => null]);
  }

  #[Test]
  public function testApplySetsAValidPlanPosition(): void
  {
    $record = $this->draftRecord('operational');
    $record->recordStatus = 'published';
    $record->facilityId = '550e8400-e29b-41d4-a716-446655440005';

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $planPosition = ['attachmentId' => '550e8400-e29b-41d4-a716-446655440099', 'x' => 0.42, 'y' => 0.17];

    $this->adapter($entityManager, $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class))
      ->apply(self::ORGANIZATION_ID, '/api/equipment/' . self::EQUIPMENT_ID, ['planPosition' => $planPosition]);

    self::assertSame($planPosition, $record->planPosition);
  }

  #[Test]
  public function testApplyRejectsAPlanPositionForFacilityLessEquipment(): void
  {
    $record = $this->draftRecord('in_stock');
    $record->recordStatus = 'published';
    $record->facilityId = null;

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Equipment must be assigned to a facility before it can be placed on a plan.');

    $this->adapter($entityManager, $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class))
      ->apply(self::ORGANIZATION_ID, '/api/equipment/' . self::EQUIPMENT_ID, [
        'planPosition' => ['attachmentId' => '550e8400-e29b-41d4-a716-446655440099', 'x' => 0.1, 'y' => 0.1],
      ]);
  }

  #[Test]
  public function testApplyRejectsAMalformedPlanPosition(): void
  {
    $record = $this->draftRecord('operational');
    $record->recordStatus = 'published';
    $record->facilityId = '550e8400-e29b-41d4-a716-446655440005';

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $this->expectException(InterventionConflictException::class);

    $this->adapter($entityManager, $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class))
      ->apply(self::ORGANIZATION_ID, '/api/equipment/' . self::EQUIPMENT_ID, [
        'planPosition' => ['attachmentId' => 'not-a-uuid', 'x' => 0.1, 'y' => 0.1],
      ]);
  }

  #[Test]
  public function testApplyClearsThePlanPositionWhenTheFacilityIsCleared(): void
  {
    $record = $this->draftRecord('in_stock');
    $record->recordStatus = 'published';
    $record->facilityId = '550e8400-e29b-41d4-a716-446655440005';
    $record->planPosition = ['attachmentId' => '550e8400-e29b-41d4-a716-446655440099', 'x' => 0.1, 'y' => 0.1];

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    $this->adapter($entityManager, $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class))
      ->apply(self::ORGANIZATION_ID, '/api/equipment/' . self::EQUIPMENT_ID, ['facility' => null]);

    self::assertNull($record->planPosition);
  }

  private function adapter(
    EntityManagerInterface $entityManager,
    EquipmentMaintenanceLogSynchronizerPort $synchronizer,
  ): EquipmentInterventionResourceAdapter {
    return new EquipmentInterventionResourceAdapter(
      $entityManager,
      $this->createStub(FacilityValidationPort::class),
      $synchronizer,
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManagerFindingDrafts(EquipmentRecord $record): EntityManagerInterface
  {
    /** @var EntityRepository<EquipmentRecord>&MockObject $repository */
    $repository = $this->createMock(EntityRepository::class);
    $repository->method('findBy')
      ->with(['interventionId' => self::INTERVENTION_ID, 'recordStatus' => 'draft'])
      ->willReturn([$record]);

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')->willReturn($repository);
    $entityManager->expects(self::once())->method('flush');

    return $entityManager;
  }

  private function draftRecord(string $status): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->interventionId = self::INTERVENTION_ID;
    $record->recordStatus = 'draft';
    $record->revision = 1;
    $record->type = 'fire_extinguisher';
    $record->status = $status;
    $record->facilityId = 'in_stock' === $status ? null : '550e8400-e29b-41d4-a716-446655440005';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }
}
