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
use Shared\Infrastructure\DataFixtures\{SeedTimeline, SeedUuid};

use function intdiv;
use function sprintf;

final class InspectionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  public const string CHECKLIST_REFERENCE = 'inspection-seed-checklist';

  public const string ANNUAL_CHECKLIST_REFERENCE = 'inspection-seed-annual-checklist';

  public const string PASSING_INSPECTION_REFERENCE = 'inspection-seed-pass';

  public const string FAILING_INSPECTION_REFERENCE = 'inspection-seed-fail';

  public const string TREND_CHECKLIST_ID = 'e6f5fe61-910b-4cd0-8d09-72af38405c64';

  private const string ADMIN_USER_NAME = 'Admin User';

  private const string TEST_USER_NAME = 'Test User';

  private const string EXTERNAL_SAFETY_SERVICES_NAME = 'External Safety Services';

  private const string SAFE_CHECK_CONSULTANTS_NAME = 'SafeCheck Consultants';

  private const string APRIL_ELEVENTH_DUE_AT = '2026-04-11T12:00:00+00:00';

  private const string AQUAFIRE_MAINTENANCE_NAME = 'AquaFire Maintenance';

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

    $checklist = $this->seedMonthlyChecklist($manager, $organization);
    $coreInspections = $this->seedCoreInspections($manager, $organization, $checklist);
    $this->seedAprilCoreInspections($manager, $organization, $checklist);
    $this->seedAdditionalEquipmentInspections($manager, $organization, $checklist);
    $this->seedCoreNonConformities($manager, $coreInspections);

    $annualChecklist = $this->seedAnnualChecklist($manager, $organization);
    $this->seedAnnualWinterInspections($manager, $organization, $annualChecklist);
    $this->seedSpringInspections($manager, $organization, $checklist, $annualChecklist);
    $this->seedJanuaryInspections($manager, $organization, $checklist, $annualChecklist);
    $this->seedFebruaryInspections($manager, $organization, $checklist);
    $this->seedMarchAprilInspections($manager, $organization, $checklist, $annualChecklist);

    $manager->flush();
  }

  private function seedMonthlyChecklist(ObjectManager $manager, OrganizationRecord $organization): ChecklistRecord
  {


    $checklist = new ChecklistRecord();
    $checklist->id = self::TREND_CHECKLIST_ID;
    $checklist->organization = $organization;
    $checklist->name = 'Monthly Equipment Check';
    $checklist->version = 'v1.0';
    $checklist->status = ChecklistStatus::ACTIVE->value;
    $checklist->createdAt = SeedTimeline::at('2026-03-01T08:00:00+00:00');
    $checklist->updatedAt = SeedTimeline::at('2026-03-01T08:00:00+00:00');
    $manager->persist($checklist);
    $this->addReference(self::CHECKLIST_REFERENCE, $checklist);

    foreach ([
      ['8cc0efe4-7aaa-4c21-a339-5d84121ff4e6', 'Visual inspection', 0],
      ['9dd8ef7e-45b4-4370-8caa-f22f4aa4d3ab', 'Pressure gauge reading', 1],
      ['44444444-4444-4444-8444-444444444444', 'Seal intact', 2],
      ['b65a2b48-6831-4453-a0a6-6aa5ac17c853', 'Sign-off', 3],
    ] as [$id, $label, $position]) {
      $item = new ChecklistItemRecord();
      $item->id = $id;
      $item->checklist = $checklist;
      $item->label = $label;
      $item->position = $position;
      $item->required = true;
      $manager->persist($item);
    }

    return $checklist;
  }

  /**
   * @return array{partial: InspectionRecord, failing: InspectionRecord, lateFail: InspectionRecord, latePartial: InspectionRecord}
   */
  private function seedCoreInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist): array
  {
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $detector */
    $detector = $this->getReference(EquipmentFixtures::DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);

    $passingInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '72caf154-79de-4216-92b8-347af0f0fe2c',
        equipmentId: $extinguisher->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-05T09:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Monthly extinguisher inspection completed successfully.',
    );
    $manager->persist($passingInspection);
    $this->addReference(self::PASSING_INSPECTION_REFERENCE, $passingInspection);

    $earlyPassInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '332806dd-44a5-4186-b108-ddd9af7c3356',
        equipmentId: $detector->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-10T09:30:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Detector test passed after routine maintenance.',
    );
    $manager->persist($earlyPassInspection);

    $partialInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'f3274415-59aa-44aa-b66d-261e913a9f26',
        equipmentId: $extinguisher->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-14T11:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Extinguisher remained operational but signage update was required.',
    );
    $manager->persist($partialInspection);

    $failingInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '120f280d-5b81-4211-a690-5883a6a3a691',
        equipmentId: $detector->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::EXTERNAL_SAFETY_SERVICES_NAME,
        result: InspectionResult::FAIL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-03-20T14:00:00+00:00'),
        inspectorOrganizationName: self::EXTERNAL_SAFETY_SERVICES_NAME,
      ),
      notes: 'Smoke detector signal did not trigger panel alert.',
    );
    $manager->persist($failingInspection);
    $this->addReference(self::FAILING_INSPECTION_REFERENCE, $failingInspection);

    $lateFailInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '1d408f0b-5ab5-4e84-bde5-caa6c1f41986',
        equipmentId: $detector->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::EXTERNAL_SAFETY_SERVICES_NAME,
        result: InspectionResult::FAIL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-24T15:00:00+00:00'),
        inspectorOrganizationName: self::EXTERNAL_SAFETY_SERVICES_NAME,
      ),
      notes: 'Detector still failed the panel acknowledgment sequence.',
    );
    $manager->persist($lateFailInspection);

    $latePartialInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '4260d1fb-c9eb-424a-9f0d-a6ad21256c02',
        equipmentId: $detector->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-03-29T09:30:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Detector recovered partially but still requires follow-up calibration.',
    );
    $manager->persist($latePartialInspection);

    return [
      'partial' => $partialInspection,
      'failing' => $failingInspection,
      'lateFail' => $lateFailInspection,
      'latePartial' => $latePartialInspection,
    ];
  }

  private function seedAprilCoreInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist): void
  {
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $siteEmergencyLighting */
    $siteEmergencyLighting = $this->getReference(EquipmentFixtures::SITE_EMERGENCY_LIGHTING_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $site */
    $site = $this->getReference(FacilityFixtures::SITE_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $buildingFireDoor */
    $buildingFireDoor = $this->getReference(EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $building */
    $building = $this->getReference(FacilityFixtures::BUILDING_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $floorOneCamera */
    $floorOneCamera = $this->getReference(EquipmentFixtures::FLOOR_ONE_CAMERA_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $floorOne */
    $floorOne = $this->getReference(FacilityFixtures::FLOOR_ONE_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $floorTwoGasDetector */
    $floorTwoGasDetector = $this->getReference(EquipmentFixtures::FLOOR_TWO_GAS_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $floorTwo */
    $floorTwo = $this->getReference(FacilityFixtures::FLOOR_TWO_REFERENCE, FacilityRecord::class);

    $recentPassInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '95a0d708-2f2e-4227-8533-ea24aaf12c42',
        equipmentId: $extinguisher->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-04-01T08:30:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Final verification passed after corrective actions.',
    );
    $manager->persist($recentPassInspection);

    $siteEmergencyLightingInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '6d6b5a05-62e0-4b82-a5b5-701c5c0804eb',
        equipmentId: $siteEmergencyLighting->id,
        facilityId: $site->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-04-04T08:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Emergency lighting autonomy test passed at site entrance.',
    );
    $manager->persist($siteEmergencyLightingInspection);

    $buildingFireDoorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'c1946361-b9e1-4820-8ef0-5f80c6ca6c54',
        equipmentId: $buildingFireDoor->id,
        facilityId: $building->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::SAFE_CHECK_CONSULTANTS_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-04-04T09:30:00+00:00'),
        inspectorOrganizationName: self::SAFE_CHECK_CONSULTANTS_NAME,
      ),
      notes: 'Fire door closes correctly but the closer needs tension adjustment.',
    );
    $manager->persist($buildingFireDoorInspection);

    $floorOneCameraInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'cb31abb9-fefb-4293-ad28-152b60e3e434',
        equipmentId: $floorOneCamera->id,
        facilityId: $floorOne->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-04-04T10:15:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Camera view, recording, and retention checks passed.',
    );
    $manager->persist($floorOneCameraInspection);

    $floorTwoGasDetectorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'b08fe499-8410-450d-a862-0131e4fdab17',
        equipmentId: $floorTwoGasDetector->id,
        facilityId: $floorTwo->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::EXTERNAL_SAFETY_SERVICES_NAME,
        result: InspectionResult::FAIL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-04-04T11:00:00+00:00'),
        inspectorOrganizationName: self::EXTERNAL_SAFETY_SERVICES_NAME,
      ),
      notes: 'Gas detector calibration drift exceeded tolerance.',
    );
    $manager->persist($floorTwoGasDetectorInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'eb036e88-ba3e-4e9e-831c-fc7f3bef8f82',
        inspection: $buildingFireDoorInspection,
        description: 'Door closer tension below expected threshold.',
        severity: NonConformitySeverity::LOW->value,
        status: NonConformityStatus::OPEN->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-04-04T09:45:00+00:00'),
        updatedAt: SeedTimeline::at('2026-04-04T09:45:00+00:00'),
        dueAt: SeedTimeline::at('2026-04-18T12:00:00+00:00'),
      ),
      notes: 'Adjustment can be handled during the next maintenance visit.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '335c4924-bb48-4a3c-b018-fd458e2116cf',
        inspection: $floorTwoGasDetectorInspection,
        description: 'Calibration drift above tolerance on gas detector channel A.',
        severity: NonConformitySeverity::HIGH->value,
        status: NonConformityStatus::IN_PROGRESS->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-04-04T11:20:00+00:00'),
        updatedAt: SeedTimeline::at('2026-04-04T11:20:00+00:00'),
        dueAt: SeedTimeline::at(self::APRIL_ELEVENTH_DUE_AT),
      ),
      notes: 'Replacement sensor requested from vendor.',
    ));
  }

  /**
   * @param array{partial: InspectionRecord, failing: InspectionRecord, lateFail: InspectionRecord, latePartial: InspectionRecord} $inspections
   */
  private function seedCoreNonConformities(ObjectManager $manager, array $inspections): void
  {
    $partialInspection = $inspections['partial'];
    $failingInspection = $inspections['failing'];
    $lateFailInspection = $inspections['lateFail'];
    $latePartialInspection = $inspections['latePartial'];



    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'f261d7e4-48af-4bf7-8b2d-2c171e50b822',
        inspection: $partialInspection,
        description: 'Pressure gauge label needed replacement.',
        severity: NonConformitySeverity::MEDIUM->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-14T11:15:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-16T09:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-03-16T09:00:00+00:00'),
      ),
      notes: 'Label replaced during the next maintenance round.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '802f138e-98a7-47ae-b271-8761d554e251',
        inspection: $failingInspection,
        description: 'Alarm propagation failed during smoke detector test.',
        severity: NonConformitySeverity::CRITICAL->value,
        status: NonConformityStatus::OPEN->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-20T14:30:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-20T14:30:00+00:00'),
        dueAt: SeedTimeline::at('2026-03-29T12:00:00+00:00'),
      ),
      notes: 'Requires panel and detector troubleshooting.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '4158f69f-e262-44a9-8421-fe92c89bcd6e',
        inspection: $failingInspection,
        description: 'Detector casing needed repositioning.',
        severity: NonConformitySeverity::LOW->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-20T14:35:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-24T11:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-03-24T11:00:00+00:00'),
      ),
      notes: 'Resolved after bracket replacement.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '1744019f-76c4-4924-9ed9-93bc745fc6dc',
        inspection: $failingInspection,
        description: 'Temporary alert delay accepted until replacement kit arrives.',
        severity: NonConformitySeverity::HIGH->value,
        status: NonConformityStatus::WAIVED->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-21T08:30:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-28T10:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-03-28T10:00:00+00:00'),
      ),
      notes: 'Risk accepted temporarily by the safety lead.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '8a5e5d6d-56eb-4c63-8ab7-e6e6018fc38c',
        inspection: $lateFailInspection,
        description: 'Control panel acknowledgment sequence still unstable.',
        severity: NonConformitySeverity::MEDIUM->value,
        status: NonConformityStatus::IN_PROGRESS->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-24T16:00:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-27T14:00:00+00:00'),
        dueAt: SeedTimeline::at('2026-04-03T12:00:00+00:00'),
      ),
      notes: 'Vendor intervention scheduled.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '373bf781-649c-46e9-ac7d-f02557ae9e80',
        inspection: $latePartialInspection,
        description: 'Calibration drift corrected after follow-up inspection.',
        severity: NonConformitySeverity::LOW->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-31T09:00:00+00:00'),
        updatedAt: SeedTimeline::at('2026-04-01T08:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-04-01T08:00:00+00:00'),
      ),
      notes: 'Resolved after recalibration and validation.',
    ));
  }

  private function seedAnnualChecklist(ObjectManager $manager, OrganizationRecord $organization): ChecklistRecord
  {


    // Annual Safety Checklist
    $annualChecklist = new ChecklistRecord();
    $annualChecklist->id = 'd0ff24d9-e54d-427d-bfa0-eb99268ffdf2';
    $annualChecklist->organization = $organization;
    $annualChecklist->name = 'Annual Safety Audit';
    $annualChecklist->version = 'v1.0';
    $annualChecklist->status = ChecklistStatus::ACTIVE->value;
    $annualChecklist->createdAt = SeedTimeline::at('2026-02-01T08:00:00+00:00');
    $annualChecklist->updatedAt = SeedTimeline::at('2026-02-01T08:00:00+00:00');
    $manager->persist($annualChecklist);
    $this->addReference(self::ANNUAL_CHECKLIST_REFERENCE, $annualChecklist);

    foreach ([
      ['3f7b1b7e-5d2d-4132-ac9c-c3eaadb2b858', 'Annual visual inspection', 0],
      ['163a95eb-4dea-430e-823c-aeabd9b86533', 'Equipment tag verification', 1],
      ['895feb24-2356-4a9c-b48f-fa54b9df7b5a', 'Documentation review', 2],
      ['ea41b391-d6fc-4f1e-8bf2-30e200967b17', 'Compliance sign-off', 3],
    ] as [$id, $label, $position]) {
      $item = new ChecklistItemRecord();
      $item->id = $id;
      $item->checklist = $annualChecklist;
      $item->label = $label;
      $item->position = $position;
      $item->required = true;
      $manager->persist($item);
    }

    return $annualChecklist;
  }

  private function seedAnnualWinterInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $annualChecklist): void
  {
    /** @var EquipmentRecord $hydrant */
    $hydrant = $this->getReference(EquipmentFixtures::HYDRANT_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $sprinkler */
    $sprinkler = $this->getReference(EquipmentFixtures::SPRINKLER_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $alarmPanel */
    $alarmPanel = $this->getReference(EquipmentFixtures::ALARM_PANEL_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);

    // February – hydrant annual inspection (PASS, closed)
    $hydrantPassInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'e5c40817-6dae-4095-b81d-7580db679237',
        equipmentId: $hydrant->id,
        facilityId: null,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-02-10T10:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Annual hydrant inspection passed with no deficiencies.',
    );
    $manager->persist($hydrantPassInspection);

    // February – sprinkler inspection (PASS, closed)
    $sprinklerPassInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'd6d0395c-5bfe-418b-8aac-684469c5b114',
        equipmentId: $sprinkler->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::AQUAFIRE_MAINTENANCE_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-02-20T09:00:00+00:00'),
        inspectorOrganizationName: self::AQUAFIRE_MAINTENANCE_NAME,
      ),
      notes: 'Sprinkler head coverage confirmed, flow test passed.',
    );
    $manager->persist($sprinklerPassInspection);

    // February – alarm panel inspection (PARTIAL, closed) with non-conformity
    $alarmPanelInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '2fcce4e6-5464-46ee-93ac-fceabc6fbe1a',
        equipmentId: $alarmPanel->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::SAFE_CHECK_CONSULTANTS_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-02-25T13:00:00+00:00'),
        inspectorOrganizationName: self::SAFE_CHECK_CONSULTANTS_NAME,
      ),
      notes: 'Panel functional but event log exceeded manufacturer threshold.',
    );
    $manager->persist($alarmPanelInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'c7e889d0-639a-45e7-951f-e9c4290d5051',
        inspection: $alarmPanelInspection,
        description: 'Event log buffer over 80% capacity, requires archiving.',
        severity: NonConformitySeverity::MEDIUM->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-02-25T13:30:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-02T10:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-03-02T10:00:00+00:00'),
      ),
      notes: 'Log archived and buffer reset by certified technician.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: '5eacfccc-77fb-487a-a880-4b72b1176952',
        inspection: $alarmPanelInspection,
        description: 'Zone 3 supervision fault indicator occasionally flashing.',
        severity: NonConformitySeverity::LOW->value,
        status: NonConformityStatus::IN_PROGRESS->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-02-25T13:35:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-05T09:00:00+00:00'),
        dueAt: SeedTimeline::at('2026-04-10T12:00:00+00:00'),
      ),
      notes: 'Wiring inspection scheduled, intermittent fault under investigation.',
    ));
  }

  private function seedSpringInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist, ChecklistRecord $annualChecklist): void
  {
    /** @var EquipmentRecord $heatDetector */
    $heatDetector = $this->getReference(EquipmentFixtures::HEAT_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $this->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $area */
    $area = $this->getReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $sprinkler */
    $sprinkler = $this->getReference(EquipmentFixtures::SPRINKLER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);

    // March – heat detector in storage room (DRAFT, in progress)
    $heatDetectorDraftInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '2c83b788-eeaf-40ff-abfd-cedb53e9b4ae',
        equipmentId: $heatDetector->id,
        facilityId: $storageRoom->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::DRAFT->value,
        performedAt: SeedTimeline::at('2026-03-18T08:00:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: null,
    );
    $manager->persist($heatDetectorDraftInspection);

    // April – extinguisher in area / server room (PASS, closed)
    $extinguisherAreaInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '201feedf-a814-496e-883b-be8aba0e8212',
        equipmentId: $extinguisher->id,
        facilityId: $area->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-04-02T09:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Extinguisher redeployed to server room entrance after last maintenance.',
    );
    $manager->persist($extinguisherAreaInspection);

    // April – sprinkler re-check (SUBMITTED, awaiting review)
    $sprinklerRecheckInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '498bd754-7b1c-4b41-814b-6b60eed12a98',
        equipmentId: $sprinkler->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-04-03T07:30:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'One sprinkler head showed minor corrosion, replacement ordered.',
    );
    $manager->persist($sprinklerRecheckInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'd216b5e9-e74c-46d8-822e-119587ffbd4b',
        inspection: $sprinklerRecheckInspection,
        description: 'Corrosion visible on sprinkler head at position 4B.',
        severity: NonConformitySeverity::HIGH->value,
        status: NonConformityStatus::OPEN->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-04-03T07:45:00+00:00'),
        updatedAt: SeedTimeline::at('2026-04-03T07:45:00+00:00'),
        dueAt: SeedTimeline::at('2026-04-17T12:00:00+00:00'),
      ),
      notes: 'Replacement part ordered, estimated delivery within 10 days.',
    ));
  }

  private function seedJanuaryInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist, ChecklistRecord $annualChecklist): void
  {
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $detector */
    $detector = $this->getReference(EquipmentFixtures::DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $area */
    $area = $this->getReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $hydrant */
    $hydrant = $this->getReference(EquipmentFixtures::HYDRANT_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $alarmPanel */
    $alarmPanel = $this->getReference(EquipmentFixtures::ALARM_PANEL_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $sprinkler */
    $sprinkler = $this->getReference(EquipmentFixtures::SPRINKLER_REFERENCE, EquipmentRecord::class);

    // ── January ─────────────────────────────────────────────────────────────

    // Jan 6 – extinguisher Zone A (PASS, closed)
    $janExtinguisherInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'cf5ee49b-bf8e-4bf6-ac2e-b0a7ad98e715',
        equipmentId: $extinguisher->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-01-06T09:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'January routine check – extinguisher in good condition.',
    );
    $manager->persist($janExtinguisherInspection);

    // Jan 12 – smoke detector server room (PASS, closed)
    $janDetectorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'e2e136bb-befa-414e-9a2b-5a3cec42a0d8',
        equipmentId: $detector->id,
        facilityId: $area->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-01-12T10:30:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Smoke detector operational. Battery level nominal.',
    );
    $manager->persist($janDetectorInspection);

    // Jan 19 – hydrant (FAIL, closed) + NC
    $janHydrantInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'fec7240e-c55f-4f46-8edd-de4bdd685fec',
        equipmentId: $hydrant->id,
        facilityId: null,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::AQUAFIRE_MAINTENANCE_NAME,
        result: InspectionResult::FAIL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-01-19T14:00:00+00:00'),
        inspectorOrganizationName: self::AQUAFIRE_MAINTENANCE_NAME,
      ),
      notes: 'Hydrant coupling thread worn, pressure drop observed.',
    );
    $manager->persist($janHydrantInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'f4c4a0e4-eefa-4800-9f42-ed9bcfd90509',
        inspection: $janHydrantInspection,
        description: 'Hydrant coupling thread worn, coupling replacement required.',
        severity: NonConformitySeverity::HIGH->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-01-19T14:30:00+00:00'),
        updatedAt: SeedTimeline::at('2026-02-03T11:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-02-03T11:00:00+00:00'),
      ),
      notes: 'Coupling replaced by certified technician on Feb 3.',
    ));

    // Jan 23 – alarm panel initial check (PASS, closed)
    $janAlarmInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '1b8b0174-85e8-4138-8fe4-28d1ae12164f',
        equipmentId: $alarmPanel->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-01-23T11:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Alarm panel self-test passed. All zones responsive.',
    );
    $manager->persist($janAlarmInspection);

    // Jan 28 – sprinkler initial commissioning check (PASS, closed)
    $janSprinklerInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'ff2f22c4-4020-4605-8011-8b4747b2ab63',
        equipmentId: $sprinkler->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::AQUAFIRE_MAINTENANCE_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-01-28T09:00:00+00:00'),
        inspectorOrganizationName: self::AQUAFIRE_MAINTENANCE_NAME,
      ),
      notes: 'Post-installation commissioning test passed.',
    );
    $manager->persist($janSprinklerInspection);
  }

  private function seedFebruaryInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist): void
  {
    /** @var EquipmentRecord $heatDetector */
    $heatDetector = $this->getReference(EquipmentFixtures::HEAT_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $this->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $this->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);

    // ── February extras ──────────────────────────────────────────────────────

    // Feb 5 – heat detector storage room (PASS, closed)
    $febHeatDetectorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '1ce89757-65ee-42ee-a41c-e090dc769741',
        equipmentId: $heatDetector->id,
        facilityId: $storageRoom->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-02-05T08:30:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Heat detector response time within spec.',
    );
    $manager->persist($febHeatDetectorInspection);

    // Feb 14 – extinguisher Zone A (PARTIAL, closed) + NC
    $febExtinguisherInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '0da23966-5101-4e7d-9e2f-d631229e43f1',
        equipmentId: $extinguisher->id,
        facilityId: $zone->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-02-14T10:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Extinguisher functional but service sticker expired.',
    );
    $manager->persist($febExtinguisherInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'f6a552b1-7c2c-42ca-aeec-243561e8ac51',
        inspection: $febExtinguisherInspection,
        description: 'Service sticker expired – maintenance log not updated after last charge.',
        severity: NonConformitySeverity::LOW->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-02-14T10:15:00+00:00'),
        updatedAt: SeedTimeline::at('2026-02-15T09:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-02-15T09:00:00+00:00'),
      ),
      notes: 'Sticker updated and log corrected on site.',
    ));
  }

  private function seedMarchAprilInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist, ChecklistRecord $annualChecklist): void
  {
    /** @var EquipmentRecord $sprinkler */
    $sprinkler = $this->getReference(EquipmentFixtures::SPRINKLER_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $alarmPanel */
    $alarmPanel = $this->getReference(EquipmentFixtures::ALARM_PANEL_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $heatDetector */
    $heatDetector = $this->getReference(EquipmentFixtures::HEAT_DETECTOR_REFERENCE, EquipmentRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $this->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);
    /** @var EquipmentRecord $hydrant */
    $hydrant = $this->getReference(EquipmentFixtures::HYDRANT_REFERENCE, EquipmentRecord::class);

    // ── March extras ─────────────────────────────────────────────────────────

    // Mar 4 – sprinkler Zone B (PASS, closed)
    $marSprinklerInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '59850189-704e-46ae-a944-bf5d56a1b02e',
        equipmentId: $sprinkler->id,
        facilityId: $zoneB->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::TEST_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-04T09:00:00+00:00'),
        inspectorUserId: 'b2c3d4e5-f6a7-4901-8cde-f23456789012',
      ),
      notes: 'Sprinkler monthly check passed. No anomaly detected.',
    );
    $manager->persist($marSprinklerInspection);

    // Mar 11 – alarm panel (FAIL, submitted) + NC
    $marAlarmFailInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '6127483b-a9e8-4e2b-8f5e-181b92e77887',
        equipmentId: $alarmPanel->id,
        facilityId: $zoneB->id,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::SAFE_CHECK_CONSULTANTS_NAME,
        result: InspectionResult::FAIL->value,
        status: InspectionStatus::SUBMITTED->value,
        performedAt: SeedTimeline::at('2026-03-11T13:30:00+00:00'),
        inspectorOrganizationName: self::SAFE_CHECK_CONSULTANTS_NAME,
      ),
      notes: 'Zone 2 failed to report to central panel during test sequence.',
    );
    $manager->persist($marAlarmFailInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'd5e1ca5b-1db9-422d-821f-949ed0a2d016',
        inspection: $marAlarmFailInspection,
        description: 'Zone 2 end-of-line resistor missing, zone supervision lost.',
        severity: NonConformitySeverity::CRITICAL->value,
        status: NonConformityStatus::IN_PROGRESS->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-11T14:00:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-28T09:00:00+00:00'),
        dueAt: SeedTimeline::at(self::APRIL_ELEVENTH_DUE_AT),
      ),
      notes: 'Wire pinched behind panel cabinet. Repair kit ordered.',
    ));

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'bde3e5b7-eee5-4882-9501-c872edb8a58c',
        inspection: $marAlarmFailInspection,
        description: 'Remote annunciator display blank during Zone 2 test.',
        severity: NonConformitySeverity::MEDIUM->value,
        status: NonConformityStatus::OPEN->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-11T14:05:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-11T14:05:00+00:00'),
        dueAt: SeedTimeline::at(self::APRIL_ELEVENTH_DUE_AT),
      ),
      notes: 'Likely caused by same wiring fault. Pending repair validation.',
    ));

    // Mar 17 – heat detector storage room (PARTIAL, closed) + NC
    $marHeatDetectorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '882b7a7b-8644-4fb8-8c12-1c031522695e',
        equipmentId: $heatDetector->id,
        facilityId: $storageRoom->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PARTIAL->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-17T10:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Heat detector slow to respond during candle test, within tolerance but flagged.',
    );
    $manager->persist($marHeatDetectorInspection);

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: 'c1b915f6-7204-4999-a5ef-09fb7014aac6',
        inspection: $marHeatDetectorInspection,
        description: 'Response time 8.2 s vs. 6 s spec – sensor aging suspected.',
        severity: NonConformitySeverity::MEDIUM->value,
        status: NonConformityStatus::DONE->value,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: SeedTimeline::at('2026-03-17T10:20:00+00:00'),
        updatedAt: SeedTimeline::at('2026-03-25T10:00:00+00:00'),
        resolvedAt: SeedTimeline::at('2026-03-25T10:00:00+00:00'),
      ),
      notes: 'Sensor replaced under warranty. Re-test confirmed 5.8 s response.',
    ));

    // Mar 26 – hydrant (PASS, closed) post-repair validation
    $marHydrantInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: '1afcb6a3-3a82-495d-9a6c-1df29828efbd',
        equipmentId: $hydrant->id,
        facilityId: null,
        checklistId: $annualChecklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::EXTERNAL->value,
        inspectorName: self::AQUAFIRE_MAINTENANCE_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-03-26T09:00:00+00:00'),
        inspectorOrganizationName: self::AQUAFIRE_MAINTENANCE_NAME,
      ),
      notes: 'Post-repair validation passed. Coupling threads confirmed sound.',
    );
    $manager->persist($marHydrantInspection);

    // ── April extras ─────────────────────────────────────────────────────────

    // Apr 3 – heat detector (PASS, closed) after sensor replacement
    $aprHeatDetectorInspection = $this->createInspection(
      organization: $organization,
      identity: new SeedInspectionIdentity(
        id: 'e6e3b2fa-3fab-4aac-b686-50529bbc7c56',
        equipmentId: $heatDetector->id,
        facilityId: $storageRoom->id,
        checklistId: $checklist->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: InspectorType::USER->value,
        inspectorName: self::ADMIN_USER_NAME,
        result: InspectionResult::PASS->value,
        status: InspectionStatus::CLOSED->value,
        performedAt: SeedTimeline::at('2026-04-03T08:00:00+00:00'),
        inspectorUserId: 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890',
      ),
      notes: 'Follow-up check after sensor replacement. Response time compliant.',
    );
    $manager->persist($aprHeatDetectorInspection);
  }

  private function seedAdditionalEquipmentInspections(ObjectManager $manager, OrganizationRecord $organization, ChecklistRecord $checklist): void
  {
    $indexes = ['inspection' => 0, 'nonConformity' => 0];
    foreach (EquipmentFixtures::ADDITIONAL_EQUIPMENT_SEEDS as $equipmentIndex => $seed) {
      /** @var EquipmentRecord $equipment */
      $equipment = $this->getReference($seed['reference'], EquipmentRecord::class);
      $context = [
        'organization' => $organization,
        'checklist' => $checklist,
        'equipment' => $equipment,
        'locationLabel' => $seed['locationLabel'],
      ];

      for ($inspectionIndex = 0; $inspectionIndex < 3; ++$inspectionIndex) {
        $this->seedAdditionalInspection($manager, $context, $equipmentIndex, $inspectionIndex, $indexes);
      }
    }
  }

  /**
   * @param array{organization: OrganizationRecord, checklist: ChecklistRecord, equipment: EquipmentRecord, locationLabel: string} $context
   * @param array{inspection: int, nonConformity: int} $indexes
   */
  private function seedAdditionalInspection(ObjectManager $manager, array $context, int $equipmentIndex, int $inspectionIndex, array &$indexes): void
  {
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
    $performedAt = SeedTimeline::at(sprintf(
      '2026-04-%02dT%02d:00:00+00:00',
      6 + intdiv($equipmentIndex, 4),
      8 + $inspectionIndex,
    ));
    $inspectorName = 0 === $inspectionIndex % 2 ? self::ADMIN_USER_NAME : self::TEST_USER_NAME;
    if (InspectorType::EXTERNAL->value === $inspectorType) {
      $inspectorName = self::SAFE_CHECK_CONSULTANTS_NAME;
    }
    $inspectorUserId = null;
    if (InspectorType::USER->value === $inspectorType) {
      $inspectorUserId = 0 === $inspectionIndex % 2
        ? 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890'
        : 'b2c3d4e5-f6a7-4901-8cde-f23456789012';
    }

    $inspection = $this->createInspection(
      organization: $context['organization'],
      identity: new SeedInspectionIdentity(
        id: SeedUuid::from(sprintf('inspection-bulk:%d', $indexes['inspection']++)),
        equipmentId: $context['equipment']->id,
        facilityId: $context['equipment']->facilityId,
        checklistId: $context['checklist']->id,
      ),
      observation: new SeedInspectionObservation(
        inspectorType: $inspectorType,
        inspectorName: $inspectorName,
        result: $result,
        status: $status,
        performedAt: $performedAt,
        inspectorUserId: $inspectorUserId,
        inspectorOrganizationName: InspectorType::EXTERNAL->value === $inspectorType ? self::SAFE_CHECK_CONSULTANTS_NAME : null,
      ),
      notes: sprintf('Seed inspection %d for %s.', $inspectionIndex + 1, $context['locationLabel']),
    );
    $manager->persist($inspection);

    if (InspectionResult::PASS->value !== $result) {
      $this->seedAdditionalNonConformity($manager, $inspection, $result, $performedAt, $context['locationLabel'], $indexes['nonConformity']++);
    }
  }

  private function seedAdditionalNonConformity(
    ObjectManager $manager,
    InspectionRecord $inspection,
    string $result,
    DateTimeImmutable $performedAt,
    string $locationLabel,
    int $nonConformityIndex,
  ): void {
    // Severity and status are spread deterministically across all four
    // values so the register's severity breakdown is never a single flat bar.
    if (InspectionResult::FAIL->value === $result) {
      $severity = 0 === $nonConformityIndex % 3
        ? NonConformitySeverity::CRITICAL->value
        : NonConformitySeverity::HIGH->value;
    } else {
      $severity = 0 === $nonConformityIndex % 2
        ? NonConformitySeverity::LOW->value
        : NonConformitySeverity::MEDIUM->value;
    }
    $nonConformityStatus = match ($nonConformityIndex % 5) {
      0 => NonConformityStatus::IN_PROGRESS->value,
      3 => NonConformityStatus::DONE->value,
      4 => NonConformityStatus::WAIVED->value,
      default => NonConformityStatus::OPEN->value,
    };
    $isResolved = NonConformityStatus::DONE->value === $nonConformityStatus
      || NonConformityStatus::WAIVED->value === $nonConformityStatus;

    $manager->persist($this->createNonConformity(
      finding: new SeedNonConformityFinding(
        id: SeedUuid::from(sprintf('non-conformity-bulk:%d', $nonConformityIndex)),
        inspection: $inspection,
        description: InspectionResult::FAIL->value === $result
          ? sprintf('%s failed validation during seeded inspection.', $locationLabel)
          : sprintf('%s requires follow-up adjustment after seeded inspection.', $locationLabel),
        severity: $severity,
        status: $nonConformityStatus,
      ),
      timeline: new SeedNonConformityTimeline(
        createdAt: $performedAt->modify('+15 minutes'),
        updatedAt: $performedAt->modify($isResolved ? '+6 days' : '+15 minutes'),
        dueAt: $isResolved ? null : $performedAt->modify('+14 days'),
        resolvedAt: $isResolved ? $performedAt->modify('+6 days') : null,
      ),
      notes: 'Generated from dense seed fixture data.',
    ));
  }

  private function createInspection(
    OrganizationRecord $organization,
    SeedInspectionIdentity $identity,
    SeedInspectionObservation $observation,
    ?string $notes,
  ): InspectionRecord {
    $inspection = new InspectionRecord();
    $inspection->id = $identity->id;
    $inspection->organization = $organization;
    $inspection->equipmentId = $identity->equipmentId;
    $inspection->facilityId = $identity->facilityId;
    $inspection->inspectorType = $observation->inspectorType;
    $inspection->inspectorName = $observation->inspectorName;
    $inspection->inspectorUserId = $observation->inspectorUserId;
    $inspection->inspectorOrganizationName = $observation->inspectorOrganizationName;
    $inspection->result = $observation->result;
    $inspection->status = $observation->status;
    $inspection->performedAt = $observation->performedAt;
    $inspection->checklistId = $identity->checklistId;
    $inspection->notes = $notes;
    $inspection->createdAt = $observation->performedAt;
    $inspection->updatedAt = $observation->performedAt->modify('+1 hour');

    return $inspection;
  }

  private function createNonConformity(
    SeedNonConformityFinding $finding,
    SeedNonConformityTimeline $timeline,
    ?string $notes = null,
  ): NonConformityRecord {
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $finding->id;
    $nonConformity->inspection = $finding->inspection;
    $nonConformity->description = $finding->description;
    $nonConformity->severity = $finding->severity;
    $nonConformity->status = $finding->status;
    $nonConformity->dueAt = $timeline->dueAt;
    $nonConformity->resolvedAt = $timeline->resolvedAt;
    $nonConformity->notes = $notes;
    $nonConformity->createdAt = $timeline->createdAt;
    $nonConformity->updatedAt = $timeline->updatedAt;

    return $nonConformity;
  }
}
