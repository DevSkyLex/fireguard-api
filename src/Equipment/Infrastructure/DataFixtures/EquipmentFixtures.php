<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Equipment\Domain\ValueObject\{EquipmentStatus, EquipmentType};
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentMaintenanceLogRecord, EquipmentRecord, EquipmentTagRecord, TagRecord};
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

final class EquipmentFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  public const string EXTINGUISHER_REFERENCE = 'equipment-seed-extinguisher';

  public const string DETECTOR_REFERENCE = 'equipment-seed-detector';

  public const string HYDRANT_REFERENCE = 'equipment-seed-hydrant';

  public const string CRITICAL_TAG_REFERENCE = 'equipment-seed-critical-tag';

  public const string INSPECTED_TAG_REFERENCE = 'equipment-seed-inspected-tag';

  public const string SPRINKLER_REFERENCE = 'equipment-seed-sprinkler';

  public const string ALARM_PANEL_REFERENCE = 'equipment-seed-alarm-panel';

  public const string HEAT_DETECTOR_REFERENCE = 'equipment-seed-heat-detector';

  public static function getGroups(): array
  {
    return ['equipment', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [
      OrganizationFixtures::class,
      FacilityFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);
    /** @var FacilityRecord $zone */
    $zone = $this->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $area */
    $area = $this->getReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $zoneB */
    $zoneB = $this->getReference(FacilityFixtures::ZONE_B_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $this->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);

    $criticalTag = new TagRecord();
    $criticalTag->id = '33333333-3333-4333-8333-333333333331';
    $criticalTag->organization = $organization;
    $criticalTag->name = 'critical-safety';
    $criticalTag->createdAt = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $manager->persist($criticalTag);
    $this->addReference(self::CRITICAL_TAG_REFERENCE, $criticalTag);

    $inspectedTag = new TagRecord();
    $inspectedTag->id = '33333333-3333-4333-8333-333333333332';
    $inspectedTag->organization = $organization;
    $inspectedTag->name = 'annually-inspected';
    $inspectedTag->createdAt = new DateTimeImmutable('2026-03-01T09:01:00+00:00');
    $manager->persist($inspectedTag);
    $this->addReference(self::INSPECTED_TAG_REFERENCE, $inspectedTag);

    $extinguisher = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333333',
      organization: $organization,
      facilityId: $zone->id,
      type: EquipmentType::FIRE_EXTINGUISHER->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-06T08:00:00+00:00'),
      brand: 'Sicli',
      model: 'Pro 6',
      serialNumber: 'SEED-EXT-001',
      locationLabel: 'Zone A - Corridor',
    );
    $manager->persist($extinguisher);
    $this->addReference(self::EXTINGUISHER_REFERENCE, $extinguisher);

    $detector = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333334',
      organization: $organization,
      facilityId: $area->id,
      type: EquipmentType::SMOKE_DETECTOR->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-15T08:00:00+00:00'),
      brand: 'Honeywell',
      model: 'SD-42',
      serialNumber: 'SEED-SMK-001',
      locationLabel: 'Server Room - Ceiling',
    );
    $manager->persist($detector);
    $this->addReference(self::DETECTOR_REFERENCE, $detector);

    $hydrant = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333335',
      organization: $organization,
      facilityId: null,
      type: EquipmentType::HYDRANT->value,
      status: EquipmentStatus::IN_STOCK->value,
      createdAt: new DateTimeImmutable('2026-03-28T08:00:00+00:00'),
      brand: 'Desautel',
      model: 'H-120',
      serialNumber: 'SEED-HYD-001',
      locationLabel: 'Warehouse',
    );
    $manager->persist($hydrant);
    $this->addReference(self::HYDRANT_REFERENCE, $hydrant);

    $criticalLink = new EquipmentTagRecord();
    $criticalLink->equipment = $extinguisher;
    $criticalLink->tag = $criticalTag;
    $manager->persist($criticalLink);

    $inspectedLink = new EquipmentTagRecord();
    $inspectedLink->equipment = $extinguisher;
    $inspectedLink->tag = $inspectedTag;
    $manager->persist($inspectedLink);

    $detectorTagLink = new EquipmentTagRecord();
    $detectorTagLink->equipment = $detector;
    $detectorTagLink->tag = $criticalTag;
    $manager->persist($detectorTagLink);

    $attachment = new EquipmentAttachmentRecord();
    $attachment->id = '33333333-3333-4333-8333-333333333336';
    $attachment->equipment = $extinguisher;
    $attachment->fileName = 'manufacturer-spec.pdf';
    $attachment->storagePath = '/fixtures/equipment/extinguisher/manufacturer-spec.pdf';
    $attachment->mimeType = 'application/pdf';
    $attachment->size = 245760;
    $attachment->label = 'Manufacturer specification';
    $attachment->uploadedAt = new DateTimeImmutable('2026-03-06T10:00:00+00:00');
    $manager->persist($attachment);

    $maintenanceLog = new EquipmentMaintenanceLogRecord();
    $maintenanceLog->id = '33333333-3333-4333-8333-333333333337';
    $maintenanceLog->equipment = $extinguisher;
    $maintenanceLog->organizationId = OrganizationFixtures::ORGANIZATION_ID;
    $maintenanceLog->startedAt = new DateTimeImmutable('2026-03-15T08:00:00+00:00');
    $maintenanceLog->completedAt = new DateTimeImmutable('2026-03-15T12:00:00+00:00');
    $manager->persist($maintenanceLog);

    $sprinkler = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333338',
      organization: $organization,
      facilityId: $zoneB->id,
      type: EquipmentType::SPRINKLER->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-30T09:00:00+00:00'),
      brand: 'Viking',
      model: 'VK301',
      serialNumber: 'SEED-SPR-001',
      locationLabel: 'Zone B - Ceiling',
    );
    $manager->persist($sprinkler);
    $this->addReference(self::SPRINKLER_REFERENCE, $sprinkler);

    $alarmPanel = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333339',
      organization: $organization,
      facilityId: $zoneB->id,
      type: EquipmentType::FIRE_ALARM_PANEL->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-30T09:10:00+00:00'),
      brand: 'Notifier',
      model: 'NFS2-3030',
      serialNumber: 'SEED-ALP-001',
      locationLabel: 'Zone B - Wall Panel',
    );
    $manager->persist($alarmPanel);
    $this->addReference(self::ALARM_PANEL_REFERENCE, $alarmPanel);

    $heatDetector = $this->createEquipment(
      id: '33333333-3333-4333-8333-33333333333a',
      organization: $organization,
      facilityId: $storageRoom->id,
      type: EquipmentType::HEAT_DETECTOR->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-30T09:20:00+00:00'),
      brand: 'System Sensor',
      model: '5601P',
      serialNumber: 'SEED-HTD-001',
      locationLabel: 'Storage Room - Ceiling',
    );
    $manager->persist($heatDetector);
    $this->addReference(self::HEAT_DETECTOR_REFERENCE, $heatDetector);

    $manager->flush();
  }

  private function createEquipment(
    string $id,
    OrganizationRecord $organization,
    ?string $facilityId,
    string $type,
    string $status,
    DateTimeImmutable $createdAt,
    ?string $brand,
    ?string $model,
    ?string $serialNumber,
    ?string $locationLabel,
  ): EquipmentRecord {
    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->facilityId = $facilityId;
    $equipment->type = $type;
    $equipment->brand = $brand;
    $equipment->model = $model;
    $equipment->serialNumber = $serialNumber;
    $equipment->locationLabel = $locationLabel;
    $equipment->status = $status;
    $equipment->installedAt = EquipmentStatus::IN_STOCK->value === $status
      ? null
      : $createdAt->modify('+1 day');
    $equipment->commissionedAt = EquipmentStatus::OPERATIONAL->value === $status
      ? $createdAt->modify('+2 days')
      : null;
    $equipment->createdAt = $createdAt;
    $equipment->updatedAt = $createdAt->modify('+2 days');

    return $equipment;
  }
}
