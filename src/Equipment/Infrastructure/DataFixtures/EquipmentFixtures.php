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

use function intdiv;
use function sprintf;

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

  public const string SITE_EMERGENCY_LIGHTING_REFERENCE = 'equipment-seed-site-emergency-lighting';

  public const string BUILDING_FIRE_DOOR_REFERENCE = 'equipment-seed-building-fire-door';

  public const string FLOOR_ONE_CAMERA_REFERENCE = 'equipment-seed-floor-one-camera';

  public const string FLOOR_TWO_GAS_DETECTOR_REFERENCE = 'equipment-seed-floor-two-gas-detector';

  /**
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   facilityReference: string,
   *   type: string,
   *   status: string,
   *   brand: string,
   *   model: string,
   *   serialNumber: string,
   *   locationLabel: string
   * }>
   */
  public const array ADDITIONAL_EQUIPMENT_SEEDS = [
    ['reference' => 'equipment-seed-bulk-01', 'id' => '33333333-3333-4333-8333-333333333340', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-002', 'locationLabel' => 'Paris Headquarters - West exit'],
    ['reference' => 'equipment-seed-bulk-02', 'id' => '33333333-3333-4333-8333-333333333341', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3265-LV', 'serialNumber' => 'SEED-CAM-002', 'locationLabel' => 'Paris Headquarters - Parking gate'],
    ['reference' => 'equipment-seed-bulk-03', 'id' => '33333333-3333-4333-8333-333333333342', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'hydrant', 'status' => 'under_maintenance', 'brand' => 'Desautel', 'model' => 'H-100', 'serialNumber' => 'SEED-HYD-002', 'locationLabel' => 'Paris Headquarters - Courtyard'],
    ['reference' => 'equipment-seed-bulk-04', 'id' => '33333333-3333-4333-8333-333333333343', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Edge EVO', 'serialNumber' => 'SEED-ACS-001', 'locationLabel' => 'Paris Headquarters - Reception access'],
    ['reference' => 'equipment-seed-bulk-05', 'id' => '33333333-3333-4333-8333-333333333344', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Siemens', 'model' => 'Cerberus Pro FC922', 'serialNumber' => 'SEED-ALP-002', 'locationLabel' => 'Main Building - Security desk'],
    ['reference' => 'equipment-seed-bulk-06', 'id' => '33333333-3333-4333-8333-333333333345', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_door', 'status' => 'operational', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-002', 'locationLabel' => 'Main Building - Stairwell B'],
    ['reference' => 'equipment-seed-bulk-07', 'id' => '33333333-3333-4333-8333-333333333346', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 30', 'serialNumber' => 'SEED-EML-003', 'locationLabel' => 'Main Building - Central corridor'],
    ['reference' => 'equipment-seed-bulk-08', 'id' => '33333333-3333-4333-8333-333333333347', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'sprinkler', 'status' => 'under_maintenance', 'brand' => 'Viking', 'model' => 'VK302', 'serialNumber' => 'SEED-SPR-002', 'locationLabel' => 'Main Building - Atrium'],
    ['reference' => 'equipment-seed-bulk-09', 'id' => '33333333-3333-4333-8333-333333333348', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-002', 'locationLabel' => 'Floor 1 - Open space north'],
    ['reference' => 'equipment-seed-bulk-10', 'id' => '33333333-3333-4333-8333-333333333349', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 9', 'serialNumber' => 'SEED-EXT-002', 'locationLabel' => 'Floor 1 - Copy room'],
    ['reference' => 'equipment-seed-bulk-11', 'id' => '33333333-3333-4333-8333-33333333334a', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Duo', 'serialNumber' => 'SEED-EML-004', 'locationLabel' => 'Floor 1 - East corridor'],
    ['reference' => 'equipment-seed-bulk-12', 'id' => '33333333-3333-4333-8333-33333333334b', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'camera', 'status' => 'decommissioned', 'brand' => 'Axis', 'model' => 'M3045-V', 'serialNumber' => 'SEED-CAM-003', 'locationLabel' => 'Floor 1 - Legacy storage view'],
    ['reference' => 'equipment-seed-bulk-13', 'id' => '33333333-3333-4333-8333-33333333334c', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-003', 'locationLabel' => 'Floor 2 - Open space south'],
    ['reference' => 'equipment-seed-bulk-14', 'id' => '33333333-3333-4333-8333-33333333334d', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5602P', 'serialNumber' => 'SEED-HTD-002', 'locationLabel' => 'Floor 2 - Technical closet'],
    ['reference' => 'equipment-seed-bulk-15', 'id' => '33333333-3333-4333-8333-33333333334e', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'gas_detector', 'status' => 'under_maintenance', 'brand' => 'Drager', 'model' => 'Polytron 8100', 'serialNumber' => 'SEED-GAS-002', 'locationLabel' => 'Floor 2 - Battery room'],
    ['reference' => 'equipment-seed-bulk-16', 'id' => '33333333-3333-4333-8333-33333333334f', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 40', 'serialNumber' => 'SEED-ACS-002', 'locationLabel' => 'Floor 2 - Restricted office'],
    ['reference' => 'equipment-seed-bulk-17', 'id' => '33333333-3333-4333-8333-333333333350', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 5', 'serialNumber' => 'SEED-EXT-003', 'locationLabel' => 'Zone A - Meeting rooms'],
    ['reference' => 'equipment-seed-bulk-18', 'id' => '33333333-3333-4333-8333-333333333351', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'FSP-951', 'serialNumber' => 'SEED-SMK-004', 'locationLabel' => 'Zone A - Ceiling grid 1'],
    ['reference' => 'equipment-seed-bulk-19', 'id' => '33333333-3333-4333-8333-333333333352', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'M3086-V', 'serialNumber' => 'SEED-CAM-004', 'locationLabel' => 'Zone A - North corridor'],
    ['reference' => 'equipment-seed-bulk-20', 'id' => '33333333-3333-4333-8333-333333333353', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 60', 'serialNumber' => 'SEED-EML-005', 'locationLabel' => 'Zone A - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-21', 'id' => '33333333-3333-4333-8333-333333333354', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'gas_detector', 'status' => 'operational', 'brand' => 'Drager', 'model' => 'Polytron 7000', 'serialNumber' => 'SEED-GAS-003', 'locationLabel' => 'Server Room - Rack row A'],
    ['reference' => 'equipment-seed-bulk-22', 'id' => '33333333-3333-4333-8333-333333333355', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 2', 'serialNumber' => 'SEED-EXT-004', 'locationLabel' => 'Server Room - Entrance'],
    ['reference' => 'equipment-seed-bulk-23', 'id' => '33333333-3333-4333-8333-333333333356', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5603P', 'serialNumber' => 'SEED-HTD-003', 'locationLabel' => 'Server Room - Hot aisle'],
    ['reference' => 'equipment-seed-bulk-24', 'id' => '33333333-3333-4333-8333-333333333357', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3245-LV', 'serialNumber' => 'SEED-CAM-005', 'locationLabel' => 'Server Room - Door view'],
    ['reference' => 'equipment-seed-bulk-25', 'id' => '33333333-3333-4333-8333-333333333358', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-003', 'locationLabel' => 'Zone B - Ceiling line 1'],
    ['reference' => 'equipment-seed-bulk-26', 'id' => '33333333-3333-4333-8333-333333333359', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Desautel', 'model' => 'P9', 'serialNumber' => 'SEED-EXT-005', 'locationLabel' => 'Zone B - South corridor'],
    ['reference' => 'equipment-seed-bulk-27', 'id' => '33333333-3333-4333-8333-33333333335a', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-003', 'locationLabel' => 'Zone B - Local panel'],
    ['reference' => 'equipment-seed-bulk-28', 'id' => '33333333-3333-4333-8333-33333333335b', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 45', 'serialNumber' => 'SEED-EML-006', 'locationLabel' => 'Zone B - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-29', 'id' => '33333333-3333-4333-8333-33333333335c', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5604P', 'serialNumber' => 'SEED-HTD-004', 'locationLabel' => 'Storage Room - Shelf row 1'],
    ['reference' => 'equipment-seed-bulk-30', 'id' => '33333333-3333-4333-8333-33333333335d', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-45', 'serialNumber' => 'SEED-SMK-005', 'locationLabel' => 'Storage Room - Ceiling center'],
    ['reference' => 'equipment-seed-bulk-31', 'id' => '33333333-3333-4333-8333-33333333335e', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30-S', 'serialNumber' => 'SEED-FDR-003', 'locationLabel' => 'Storage Room - Fire door'],
    ['reference' => 'equipment-seed-bulk-32', 'id' => '33333333-3333-4333-8333-33333333335f', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 20', 'serialNumber' => 'SEED-ACS-003', 'locationLabel' => 'Storage Room - Badge reader'],
  ];

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
    /** @var FacilityRecord $site */
    $site = $this->getReference(FacilityFixtures::SITE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $building */
    $building = $this->getReference(FacilityFixtures::BUILDING_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $floorOne */
    $floorOne = $this->getReference(FacilityFixtures::FLOOR_ONE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $floorTwo */
    $floorTwo = $this->getReference(FacilityFixtures::FLOOR_TWO_REFERENCE, FacilityRecord::class);
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

    $siteEmergencyLighting = $this->createEquipment(
      id: '33333333-3333-4333-8333-33333333333b',
      organization: $organization,
      facilityId: $site->id,
      type: EquipmentType::EMERGENCY_LIGHTING->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-31T08:00:00+00:00'),
      brand: 'Legrand',
      model: 'BAES Eco 45',
      serialNumber: 'SEED-EML-001',
      locationLabel: 'Paris Headquarters - Main entrance',
    );
    $manager->persist($siteEmergencyLighting);
    $this->addReference(self::SITE_EMERGENCY_LIGHTING_REFERENCE, $siteEmergencyLighting);

    $buildingFireDoor = $this->createEquipment(
      id: '33333333-3333-4333-8333-33333333333c',
      organization: $organization,
      facilityId: $building->id,
      type: EquipmentType::FIRE_DOOR->value,
      status: EquipmentStatus::UNDER_MAINTENANCE->value,
      createdAt: new DateTimeImmutable('2026-03-31T08:10:00+00:00'),
      brand: 'Assa Abloy',
      model: 'EI60',
      serialNumber: 'SEED-FDR-001',
      locationLabel: 'Main Building - Stairwell A',
    );
    $manager->persist($buildingFireDoor);
    $this->addReference(self::BUILDING_FIRE_DOOR_REFERENCE, $buildingFireDoor);

    $floorOneCamera = $this->createEquipment(
      id: '33333333-3333-4333-8333-33333333333d',
      organization: $organization,
      facilityId: $floorOne->id,
      type: EquipmentType::CAMERA->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-31T08:20:00+00:00'),
      brand: 'Axis',
      model: 'M3085-V',
      serialNumber: 'SEED-CAM-001',
      locationLabel: 'Floor 1 - Elevator lobby',
    );
    $manager->persist($floorOneCamera);
    $this->addReference(self::FLOOR_ONE_CAMERA_REFERENCE, $floorOneCamera);

    $floorTwoGasDetector = $this->createEquipment(
      id: '33333333-3333-4333-8333-33333333333e',
      organization: $organization,
      facilityId: $floorTwo->id,
      type: EquipmentType::GAS_DETECTOR->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: new DateTimeImmutable('2026-03-31T08:30:00+00:00'),
      brand: 'Drager',
      model: 'Polytron 7000',
      serialNumber: 'SEED-GAS-001',
      locationLabel: 'Floor 2 - Technical closet',
    );
    $manager->persist($floorTwoGasDetector);
    $this->addReference(self::FLOOR_TWO_GAS_DETECTOR_REFERENCE, $floorTwoGasDetector);

    $facilitiesByReference = [
      FacilityFixtures::SITE_REFERENCE => $site,
      FacilityFixtures::BUILDING_REFERENCE => $building,
      FacilityFixtures::FLOOR_ONE_REFERENCE => $floorOne,
      FacilityFixtures::FLOOR_TWO_REFERENCE => $floorTwo,
      FacilityFixtures::ZONE_REFERENCE => $zone,
      FacilityFixtures::AREA_REFERENCE => $area,
      FacilityFixtures::ZONE_B_REFERENCE => $zoneB,
      FacilityFixtures::STORAGE_ROOM_REFERENCE => $storageRoom,
    ];

    foreach (self::ADDITIONAL_EQUIPMENT_SEEDS as $index => $seed) {
      $facility = $facilitiesByReference[$seed['facilityReference']];
      $createdAt = new DateTimeImmutable(sprintf('2026-04-%02dT08:%02d:00+00:00', 5 + intdiv($index, 4), ($index % 4) * 10));
      $equipment = $this->createEquipment(
        id: $seed['id'],
        organization: $organization,
        facilityId: $facility->id,
        type: $seed['type'],
        status: $seed['status'],
        createdAt: $createdAt,
        brand: $seed['brand'],
        model: $seed['model'],
        serialNumber: $seed['serialNumber'],
        locationLabel: $seed['locationLabel'],
      );
      $manager->persist($equipment);
      $this->addReference($seed['reference'], $equipment);
    }

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
