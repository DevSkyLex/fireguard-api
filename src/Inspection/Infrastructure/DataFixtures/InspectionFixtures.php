<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Domain\ValueObject\{ChecklistStatus, InspectionResult, InspectionStatus, InspectorType, NonConformitySeverity, NonConformityStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord, InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function intdiv;
use function sprintf;

final class InspectionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  public const string CHECKLIST_REFERENCE = 'inspection-seed-checklist';

  public const string ANNUAL_CHECKLIST_REFERENCE = 'inspection-seed-annual-checklist';

  public const string PASSING_INSPECTION_REFERENCE = 'inspection-seed-pass';

  public const string FAILING_INSPECTION_REFERENCE = 'inspection-seed-fail';

  public const string TREND_CHECKLIST_ID = '44444444-4444-4444-8444-444444444441';

  public static function getGroups(): array
  {
    return ['inspection', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [
      OrganizationFixtures::class,
      FacilityFixtures::class,
      EquipmentFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $detector */
    $detector = $this->getReference(EquipmentFixtures::DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $hydrant */
    $hydrant = $this->getReference(EquipmentFixtures::HYDRANT_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $sprinkler */
    $sprinkler = $this->getReference(EquipmentFixtures::SPRINKLER_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $alarmPanel */
    $alarmPanel = $this->getReference(EquipmentFixtures::ALARM_PANEL_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $heatDetector */
    $heatDetector = $this->getReference(EquipmentFixtures::HEAT_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $siteEmergencyLighting */
    $siteEmergencyLighting = $this->getReference(EquipmentFixtures::SITE_EMERGENCY_LIGHTING_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $buildingFireDoor */
    $buildingFireDoor = $this->getReference(EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $floorOneCamera */
    $floorOneCamera = $this->getReference(EquipmentFixtures::FLOOR_ONE_CAMERA_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $floorTwoGasDetector */
    $floorTwoGasDetector = $this->getReference(EquipmentFixtures::FLOOR_TWO_GAS_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $site */
    $site = $this->getReference(FacilityFixtures::SITE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $building */
    $building = $this->getReference(FacilityFixtures::BUILDING_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $floorOne */
    $floorOne = $this->getReference(FacilityFixtures::FLOOR_ONE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $floorTwo */
    $floorTwo = $this->getReference(FacilityFixtures::FLOOR_TWO_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $area */
    $area = $this->getReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $this->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);

    $checklist = new ChecklistRecord();
    $checklist->id = self::TREND_CHECKLIST_ID;
    $checklist->organization = $organization;
    $checklist->name = 'Monthly Equipment Check';
    $checklist->version = 'v1.0';
    $checklist->status = ChecklistStatus::ACTIVE->value;
    $checklist->createdAt = new DateTimeImmutable('2026-03-01T08:00:00+00:00');
    $checklist->updatedAt = new DateTimeImmutable('2026-03-01T08:00:00+00:00');
    $manager->persist($checklist);
    $this->addReference(self::CHECKLIST_REFERENCE, $checklist);

    foreach ([
      ['44444444-4444-4444-8444-444444444442', 'Visual inspection', 0],
      ['44444444-4444-4444-8444-444444444443', 'Pressure gauge reading', 1],
      ['44444444-4444-4444-8444-444444444444', 'Seal intact', 2],
      ['44444444-4444-4444-8444-444444444445', 'Sign-off', 3],
    ] as [$id, $label, $position]) {
      $item = new ChecklistItemRecord();
      $item->id = $id;
      $item->checklist = $checklist;
      $item->label = $label;
      $item->position = $position;
      $item->required = true;
      $manager->persist($item);
    }

    $passingInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444446',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-05T09:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Monthly extinguisher inspection completed successfully.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($passingInspection);
    $this->addReference(self::PASSING_INSPECTION_REFERENCE, $passingInspection);

    $earlyPassInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444450',
      organization: $organization,
      equipmentId: $detector->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-10T09:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Detector test passed after routine maintenance.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($earlyPassInspection);

    $partialInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444451',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-14T11:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Extinguisher remained operational but signage update was required.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($partialInspection);

    $failingInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444447',
      organization: $organization,
      equipmentId: $detector->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'External Safety Services',
      result: InspectionResult::FAIL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-03-20T14:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Smoke detector signal did not trigger panel alert.',
      inspectorOrganizationName: 'External Safety Services',
    );
    $manager->persist($failingInspection);
    $this->addReference(self::FAILING_INSPECTION_REFERENCE, $failingInspection);

    $lateFailInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444452',
      organization: $organization,
      equipmentId: $detector->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'External Safety Services',
      result: InspectionResult::FAIL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-24T15:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Detector still failed the panel acknowledgment sequence.',
      inspectorOrganizationName: 'External Safety Services',
    );
    $manager->persist($lateFailInspection);

    $latePartialInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444453',
      organization: $organization,
      equipmentId: $detector->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-03-29T09:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Detector recovered partially but still requires follow-up calibration.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($latePartialInspection);

    $recentPassInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444454',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-04-01T08:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Final verification passed after corrective actions.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($recentPassInspection);

    $siteEmergencyLightingInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444484',
      organization: $organization,
      equipmentId: $siteEmergencyLighting->id,
      facilityId: $site->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-04-04T08:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Emergency lighting autonomy test passed at site entrance.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($siteEmergencyLightingInspection);

    $buildingFireDoorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444485',
      organization: $organization,
      equipmentId: $buildingFireDoor->id,
      facilityId: $building->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'SafeCheck Consultants',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-04-04T09:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Fire door closes correctly but the closer needs tension adjustment.',
      inspectorOrganizationName: 'SafeCheck Consultants',
    );
    $manager->persist($buildingFireDoorInspection);

    $floorOneCameraInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444486',
      organization: $organization,
      equipmentId: $floorOneCamera->id,
      facilityId: $floorOne->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-04-04T10:15:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Camera view, recording, and retention checks passed.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($floorOneCameraInspection);

    $floorTwoGasDetectorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444487',
      organization: $organization,
      equipmentId: $floorTwoGasDetector->id,
      facilityId: $floorTwo->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'External Safety Services',
      result: InspectionResult::FAIL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-04-04T11:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Gas detector calibration drift exceeded tolerance.',
      inspectorOrganizationName: 'External Safety Services',
    );
    $manager->persist($floorTwoGasDetectorInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444488',
      inspection: $buildingFireDoorInspection,
      description: 'Door closer tension below expected threshold.',
      severity: NonConformitySeverity::LOW->value,
      status: NonConformityStatus::OPEN->value,
      createdAt: new DateTimeImmutable('2026-04-04T09:45:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-04T09:45:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-18T12:00:00+00:00'),
      notes: 'Adjustment can be handled during the next maintenance visit.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444489',
      inspection: $floorTwoGasDetectorInspection,
      description: 'Calibration drift above tolerance on gas detector channel A.',
      severity: NonConformitySeverity::HIGH->value,
      status: NonConformityStatus::IN_PROGRESS->value,
      createdAt: new DateTimeImmutable('2026-04-04T11:20:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-04T11:20:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-11T12:00:00+00:00'),
      notes: 'Replacement sensor requested from vendor.',
    ));

    $bulkInspectionIndex = 0;
    $bulkNonConformityIndex = 0;
    foreach (EquipmentFixtures::ADDITIONAL_EQUIPMENT_SEEDS as $equipmentIndex => $seed) {
      /** @var EquipmentRecord $equipment */
      $equipment = $this->getReference($seed['reference'], EquipmentRecord::class);

      for ($inspectionIndex = 0; $inspectionIndex < 3; ++$inspectionIndex) {
        $result = match (($equipmentIndex + $inspectionIndex) % 4) {
          1 => InspectionResult::PARTIAL->value,
          2 => InspectionResult::FAIL->value,
          default => InspectionResult::PASS->value,
        };
        $status = InspectionResult::PASS->value === $result || 0 === $inspectionIndex
          ? InspectionStatus::CLOSED->value
          : InspectionStatus::SUBMITTED->value;
        $inspectorType = 0 === ($equipmentIndex + $inspectionIndex) % 3
          ? InspectorType::EXTERNAL->value
          : InspectorType::USER->value;
        $performedAt = new DateTimeImmutable(sprintf(
          '2026-04-%02dT%02d:00:00+00:00',
          6 + intdiv($equipmentIndex, 4),
          8 + $inspectionIndex,
        ));

        $inspection = $this->createInspection(
          id: sprintf('44444444-4444-4444-8444-4444444445%02x', $bulkInspectionIndex++),
          organization: $organization,
          equipmentId: $equipment->id,
          facilityId: $equipment->facilityId,
          inspectorType: $inspectorType,
          inspectorName: InspectorType::EXTERNAL->value === $inspectorType ? 'SafeCheck Consultants' : (0 === $inspectionIndex % 2 ? 'Admin User' : 'Test User'),
          result: $result,
          status: $status,
          performedAt: $performedAt,
          checklistId: $checklist->id,
          notes: sprintf('Seed inspection %d for %s.', $inspectionIndex + 1, $seed['locationLabel']),
          inspectorUserId: InspectorType::USER->value === $inspectorType ? (0 === $inspectionIndex % 2 ? 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890' : 'b2c3d4e5-f6a7-4901-8cde-f23456789012') : null,
          inspectorOrganizationName: InspectorType::EXTERNAL->value === $inspectorType ? 'SafeCheck Consultants' : null,
        );
        $manager->persist($inspection);

        if (InspectionResult::PASS->value !== $result) {
          $manager->persist($this->createNonConformity(
            id: sprintf('44444444-4444-4444-8444-4444444446%02x', $bulkNonConformityIndex++),
            inspection: $inspection,
            description: InspectionResult::FAIL->value === $result
              ? sprintf('%s failed validation during seeded inspection.', $seed['locationLabel'])
              : sprintf('%s requires follow-up adjustment after seeded inspection.', $seed['locationLabel']),
            severity: InspectionResult::FAIL->value === $result ? NonConformitySeverity::HIGH->value : NonConformitySeverity::MEDIUM->value,
            status: InspectionResult::FAIL->value === $result ? NonConformityStatus::OPEN->value : NonConformityStatus::IN_PROGRESS->value,
            createdAt: $performedAt->modify('+15 minutes'),
            updatedAt: $performedAt->modify('+15 minutes'),
            dueAt: $performedAt->modify('+14 days'),
            notes: 'Generated from dense seed fixture data.',
          ));
        }
      }
    }

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444448',
      inspection: $partialInspection,
      description: 'Pressure gauge label needed replacement.',
      severity: NonConformitySeverity::MEDIUM->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-03-14T11:15:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-16T09:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-16T09:00:00+00:00'),
      notes: 'Label replaced during the next maintenance round.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444449',
      inspection: $failingInspection,
      description: 'Alarm propagation failed during smoke detector test.',
      severity: NonConformitySeverity::CRITICAL->value,
      status: NonConformityStatus::OPEN->value,
      createdAt: new DateTimeImmutable('2026-03-20T14:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-20T14:30:00+00:00'),
      dueAt: new DateTimeImmutable('2026-03-29T12:00:00+00:00'),
      notes: 'Requires panel and detector troubleshooting.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444455',
      inspection: $failingInspection,
      description: 'Detector casing needed repositioning.',
      severity: NonConformitySeverity::LOW->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-03-20T14:35:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-24T11:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-24T11:00:00+00:00'),
      notes: 'Resolved after bracket replacement.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444456',
      inspection: $failingInspection,
      description: 'Temporary alert delay accepted until replacement kit arrives.',
      severity: NonConformitySeverity::HIGH->value,
      status: NonConformityStatus::WAIVED->value,
      createdAt: new DateTimeImmutable('2026-03-21T08:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-28T10:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-28T10:00:00+00:00'),
      notes: 'Risk accepted temporarily by the safety lead.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444457',
      inspection: $lateFailInspection,
      description: 'Control panel acknowledgment sequence still unstable.',
      severity: NonConformitySeverity::MEDIUM->value,
      status: NonConformityStatus::IN_PROGRESS->value,
      createdAt: new DateTimeImmutable('2026-03-24T16:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-27T14:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-03T12:00:00+00:00'),
      notes: 'Vendor intervention scheduled.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444458',
      inspection: $latePartialInspection,
      description: 'Calibration drift corrected after follow-up inspection.',
      severity: NonConformitySeverity::LOW->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-03-31T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-01T08:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-04-01T08:00:00+00:00'),
      notes: 'Resolved after recalibration and validation.',
    ));

    // Annual Safety Checklist
    $annualChecklist = new ChecklistRecord();
    $annualChecklist->id = '44444444-4444-4444-8444-444444444460';
    $annualChecklist->organization = $organization;
    $annualChecklist->name = 'Annual Safety Audit';
    $annualChecklist->version = 'v1.0';
    $annualChecklist->status = ChecklistStatus::ACTIVE->value;
    $annualChecklist->createdAt = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $annualChecklist->updatedAt = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $manager->persist($annualChecklist);
    $this->addReference(self::ANNUAL_CHECKLIST_REFERENCE, $annualChecklist);

    foreach ([
      ['44444444-4444-4444-8444-444444444461', 'Annual visual inspection', 0],
      ['44444444-4444-4444-8444-444444444462', 'Equipment tag verification', 1],
      ['44444444-4444-4444-8444-444444444463', 'Documentation review', 2],
      ['44444444-4444-4444-8444-444444444464', 'Compliance sign-off', 3],
    ] as [$id, $label, $position]) {
      $item = new ChecklistItemRecord();
      $item->id = $id;
      $item->checklist = $annualChecklist;
      $item->label = $label;
      $item->position = $position;
      $item->required = true;
      $manager->persist($item);
    }

    // February – hydrant annual inspection (PASS, closed)
    $hydrantPassInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444465',
      organization: $organization,
      equipmentId: $hydrant->id,
      facilityId: null,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-02-10T10:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Annual hydrant inspection passed with no deficiencies.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($hydrantPassInspection);

    // February – sprinkler inspection (PASS, closed)
    $sprinklerPassInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444466',
      organization: $organization,
      equipmentId: $sprinkler->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'AquaFire Maintenance',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-02-20T09:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Sprinkler head coverage confirmed, flow test passed.',
      inspectorOrganizationName: 'AquaFire Maintenance',
    );
    $manager->persist($sprinklerPassInspection);

    // February – alarm panel inspection (PARTIAL, closed) with non-conformity
    $alarmPanelInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444467',
      organization: $organization,
      equipmentId: $alarmPanel->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'SafeCheck Consultants',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-02-25T13:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Panel functional but event log exceeded manufacturer threshold.',
      inspectorOrganizationName: 'SafeCheck Consultants',
    );
    $manager->persist($alarmPanelInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444470',
      inspection: $alarmPanelInspection,
      description: 'Event log buffer over 80% capacity, requires archiving.',
      severity: NonConformitySeverity::MEDIUM->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-02-25T13:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-02T10:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-02T10:00:00+00:00'),
      notes: 'Log archived and buffer reset by certified technician.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444471',
      inspection: $alarmPanelInspection,
      description: 'Zone 3 supervision fault indicator occasionally flashing.',
      severity: NonConformitySeverity::LOW->value,
      status: NonConformityStatus::IN_PROGRESS->value,
      createdAt: new DateTimeImmutable('2026-02-25T13:35:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-05T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-10T12:00:00+00:00'),
      notes: 'Wiring inspection scheduled, intermittent fault under investigation.',
    ));

    // March – heat detector in storage room (DRAFT, in progress)
    $heatDetectorDraftInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444468',
      organization: $organization,
      equipmentId: $heatDetector->id,
      facilityId: $storageRoom->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::DRAFT->value,
      performedAt: new DateTimeImmutable('2026-03-18T08:00:00+00:00'),
      checklistId: $checklist->id,
      notes: null,
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($heatDetectorDraftInspection);

    // April – extinguisher in area / server room (PASS, closed)
    $extinguisherAreaInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444469',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $area->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-04-02T09:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Extinguisher redeployed to server room entrance after last maintenance.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($extinguisherAreaInspection);

    // April – sprinkler re-check (SUBMITTED, awaiting review)
    $sprinklerRecheckInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-44444444446a',
      organization: $organization,
      equipmentId: $sprinkler->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-04-03T07:30:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'One sprinkler head showed minor corrosion, replacement ordered.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($sprinklerRecheckInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444472',
      inspection: $sprinklerRecheckInspection,
      description: 'Corrosion visible on sprinkler head at position 4B.',
      severity: NonConformitySeverity::HIGH->value,
      status: NonConformityStatus::OPEN->value,
      createdAt: new DateTimeImmutable('2026-04-03T07:45:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-04-03T07:45:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-17T12:00:00+00:00'),
      notes: 'Replacement part ordered, estimated delivery within 10 days.',
    ));

    // ── January ─────────────────────────────────────────────────────────────

    // Jan 6 – extinguisher Zone A (PASS, closed)
    $janExtinguisherInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444473',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-01-06T09:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'January routine check – extinguisher in good condition.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($janExtinguisherInspection);

    // Jan 12 – smoke detector server room (PASS, closed)
    $janDetectorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444474',
      organization: $organization,
      equipmentId: $detector->id,
      facilityId: $area->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-01-12T10:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Smoke detector operational. Battery level nominal.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($janDetectorInspection);

    // Jan 19 – hydrant (FAIL, closed) + NC
    $janHydrantInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444475',
      organization: $organization,
      equipmentId: $hydrant->id,
      facilityId: null,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'AquaFire Maintenance',
      result: InspectionResult::FAIL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-01-19T14:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Hydrant coupling thread worn, pressure drop observed.',
      inspectorOrganizationName: 'AquaFire Maintenance',
    );
    $manager->persist($janHydrantInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444476',
      inspection: $janHydrantInspection,
      description: 'Hydrant coupling thread worn, coupling replacement required.',
      severity: NonConformitySeverity::HIGH->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-01-19T14:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-03T11:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-02-03T11:00:00+00:00'),
      notes: 'Coupling replaced by certified technician on Feb 3.',
    ));

    // Jan 23 – alarm panel initial check (PASS, closed)
    $janAlarmInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444477',
      organization: $organization,
      equipmentId: $alarmPanel->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-01-23T11:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Alarm panel self-test passed. All zones responsive.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($janAlarmInspection);

    // Jan 28 – sprinkler initial commissioning check (PASS, closed)
    $janSprinklerInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444478',
      organization: $organization,
      equipmentId: $sprinkler->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'AquaFire Maintenance',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-01-28T09:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Post-installation commissioning test passed.',
      inspectorOrganizationName: 'AquaFire Maintenance',
    );
    $manager->persist($janSprinklerInspection);

    // ── February extras ──────────────────────────────────────────────────────

    // Feb 5 – heat detector storage room (PASS, closed)
    $febHeatDetectorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444479',
      organization: $organization,
      equipmentId: $heatDetector->id,
      facilityId: $storageRoom->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-02-05T08:30:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Heat detector response time within spec.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($febHeatDetectorInspection);

    // Feb 14 – extinguisher Zone A (PARTIAL, closed) + NC
    $febExtinguisherInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-44444444447a',
      organization: $organization,
      equipmentId: $extinguisher->id,
      facilityId: $zone->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-02-14T10:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Extinguisher functional but service sticker expired.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($febExtinguisherInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-44444444447b',
      inspection: $febExtinguisherInspection,
      description: 'Service sticker expired – maintenance log not updated after last charge.',
      severity: NonConformitySeverity::LOW->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-02-14T10:15:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-15T09:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-02-15T09:00:00+00:00'),
      notes: 'Sticker updated and log corrected on site.',
    ));

    // ── March extras ─────────────────────────────────────────────────────────

    // Mar 4 – sprinkler Zone B (PASS, closed)
    $marSprinklerInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-44444444447c',
      organization: $organization,
      equipmentId: $sprinkler->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Test User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-04T09:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Sprinkler monthly check passed. No anomaly detected.',
      inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
    );
    $manager->persist($marSprinklerInspection);

    // Mar 11 – alarm panel (FAIL, submitted) + NC
    $marAlarmFailInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-44444444447d',
      organization: $organization,
      equipmentId: $alarmPanel->id,
      facilityId: $zoneB->id,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'SafeCheck Consultants',
      result: InspectionResult::FAIL->value,
      status: InspectionStatus::SUBMITTED->value,
      performedAt: new DateTimeImmutable('2026-03-11T13:30:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Zone 2 failed to report to central panel during test sequence.',
      inspectorOrganizationName: 'SafeCheck Consultants',
    );
    $manager->persist($marAlarmFailInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-44444444447e',
      inspection: $marAlarmFailInspection,
      description: 'Zone 2 end-of-line resistor missing, zone supervision lost.',
      severity: NonConformitySeverity::CRITICAL->value,
      status: NonConformityStatus::IN_PROGRESS->value,
      createdAt: new DateTimeImmutable('2026-03-11T14:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-28T09:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-11T12:00:00+00:00'),
      notes: 'Wire pinched behind panel cabinet. Repair kit ordered.',
    ));

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-44444444447f',
      inspection: $marAlarmFailInspection,
      description: 'Remote annunciator display blank during Zone 2 test.',
      severity: NonConformitySeverity::MEDIUM->value,
      status: NonConformityStatus::OPEN->value,
      createdAt: new DateTimeImmutable('2026-03-11T14:05:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-11T14:05:00+00:00'),
      dueAt: new DateTimeImmutable('2026-04-11T12:00:00+00:00'),
      notes: 'Likely caused by same wiring fault. Pending repair validation.',
    ));

    // Mar 17 – heat detector storage room (PARTIAL, closed) + NC
    $marHeatDetectorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444480',
      organization: $organization,
      equipmentId: $heatDetector->id,
      facilityId: $storageRoom->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PARTIAL->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-17T10:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Heat detector slow to respond during candle test, within tolerance but flagged.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($marHeatDetectorInspection);

    $manager->persist($this->createNonConformity(
      id: '44444444-4444-4444-8444-444444444481',
      inspection: $marHeatDetectorInspection,
      description: 'Response time 8.2 s vs. 6 s spec – sensor aging suspected.',
      severity: NonConformitySeverity::MEDIUM->value,
      status: NonConformityStatus::DONE->value,
      createdAt: new DateTimeImmutable('2026-03-17T10:20:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-03-25T10:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-03-25T10:00:00+00:00'),
      notes: 'Sensor replaced under warranty. Re-test confirmed 5.8 s response.',
    ));

    // Mar 26 – hydrant (PASS, closed) post-repair validation
    $marHydrantInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444482',
      organization: $organization,
      equipmentId: $hydrant->id,
      facilityId: null,
      inspectorType: InspectorType::EXTERNAL->value,
      inspectorName: 'AquaFire Maintenance',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-03-26T09:00:00+00:00'),
      checklistId: $annualChecklist->id,
      notes: 'Post-repair validation passed. Coupling threads confirmed sound.',
      inspectorOrganizationName: 'AquaFire Maintenance',
    );
    $manager->persist($marHydrantInspection);

    // ── April extras ─────────────────────────────────────────────────────────

    // Apr 3 – heat detector (PASS, closed) after sensor replacement
    $aprHeatDetectorInspection = $this->createInspection(
      id: '44444444-4444-4444-8444-444444444483',
      organization: $organization,
      equipmentId: $heatDetector->id,
      facilityId: $storageRoom->id,
      inspectorType: InspectorType::USER->value,
      inspectorName: 'Admin User',
      result: InspectionResult::PASS->value,
      status: InspectionStatus::CLOSED->value,
      performedAt: new DateTimeImmutable('2026-04-03T08:00:00+00:00'),
      checklistId: $checklist->id,
      notes: 'Follow-up check after sensor replacement. Response time compliant.',
      inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
    );
    $manager->persist($aprHeatDetectorInspection);

    $manager->flush();
  }

  private function createInspection(
    string $id,
    OrganizationRecord $organization,
    string $equipmentId,
    ?string $facilityId,
    string $inspectorType,
    string $inspectorName,
    string $result,
    string $status,
    DateTimeImmutable $performedAt,
    ?string $checklistId,
    ?string $notes,
    ?string $inspectorUserId = null,
    ?string $inspectorOrganizationName = null,
  ): InspectionRecord {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = $equipmentId;
    $inspection->facilityId = $facilityId;
    $inspection->inspectorType = $inspectorType;
    $inspection->inspectorName = $inspectorName;
    $inspection->inspectorUserId = $inspectorUserId;
    $inspection->inspectorOrganizationName = $inspectorOrganizationName;
    $inspection->result = $result;
    $inspection->status = $status;
    $inspection->performedAt = $performedAt;
    $inspection->checklistId = $checklistId;
    $inspection->notes = $notes;
    $inspection->createdAt = $performedAt;
    $inspection->updatedAt = $performedAt->modify('+1 hour');

    return $inspection;
  }

  private function createNonConformity(
    string $id,
    InspectionRecord $inspection,
    string $description,
    string $severity,
    string $status,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $dueAt = null,
    ?DateTimeImmutable $resolvedAt = null,
    ?string $notes = null,
  ): NonConformityRecord {
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $id;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = $description;
    $nonConformity->severity = $severity;
    $nonConformity->status = $status;
    $nonConformity->dueAt = $dueAt;
    $nonConformity->resolvedAt = $resolvedAt;
    $nonConformity->notes = $notes;
    $nonConformity->createdAt = $createdAt;
    $nonConformity->updatedAt = $updatedAt;

    return $nonConformity;
  }
}
