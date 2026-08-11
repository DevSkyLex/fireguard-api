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
use Shared\Infrastructure\DataFixtures\{SeedTimeline, SeedUuid};

use function count;
use function intdiv;
use function sprintf;
use function str_replace;
use function ucfirst;

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
    ['reference' => 'equipment-seed-bulk-01', 'id' => '0cab488f-5780-4a8f-9d50-b14d665fba73', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-002', 'locationLabel' => 'Paris Headquarters - West exit'],
    ['reference' => 'equipment-seed-bulk-02', 'id' => '33333333-3333-4333-8333-333333333341', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3265-LV', 'serialNumber' => 'SEED-CAM-002', 'locationLabel' => 'Paris Headquarters - Parking gate'],
    ['reference' => 'equipment-seed-bulk-03', 'id' => '33333333-3333-4333-8333-333333333342', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'hydrant', 'status' => 'under_maintenance', 'brand' => 'Desautel', 'model' => 'H-100', 'serialNumber' => 'SEED-HYD-002', 'locationLabel' => 'Paris Headquarters - Courtyard'],
    ['reference' => 'equipment-seed-bulk-04', 'id' => '33333333-3333-4333-8333-333333333343', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Edge EVO', 'serialNumber' => 'SEED-ACS-001', 'locationLabel' => 'Paris Headquarters - Reception access'],
    ['reference' => 'equipment-seed-bulk-05', 'id' => '33333333-3333-4333-8333-333333333344', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Siemens', 'model' => 'Cerberus Pro FC922', 'serialNumber' => 'SEED-ALP-002', 'locationLabel' => 'Main Building - Security desk'],
    ['reference' => 'equipment-seed-bulk-06', 'id' => '33333333-3333-4333-8333-333333333345', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_door', 'status' => 'operational', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-002', 'locationLabel' => 'Main Building - Stairwell B'],
    ['reference' => 'equipment-seed-bulk-07', 'id' => '33333333-3333-4333-8333-333333333346', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 30', 'serialNumber' => 'SEED-EML-003', 'locationLabel' => 'Main Building - Central corridor'],
    ['reference' => 'equipment-seed-bulk-08', 'id' => '33333333-3333-4333-8333-333333333347', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'sprinkler', 'status' => 'under_maintenance', 'brand' => 'Viking', 'model' => 'VK302', 'serialNumber' => 'SEED-SPR-002', 'locationLabel' => 'Main Building - Atrium'],
    ['reference' => 'equipment-seed-bulk-09', 'id' => 'df71b23a-1e76-4fdf-a8d7-bbbd9c6a41bb', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-002', 'locationLabel' => 'Floor 1 - Open space north'],
    ['reference' => 'equipment-seed-bulk-10', 'id' => '3fee3091-9151-4c32-bc17-69a988cc1bb1', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 9', 'serialNumber' => 'SEED-EXT-002', 'locationLabel' => 'Floor 1 - Copy room'],
    ['reference' => 'equipment-seed-bulk-11', 'id' => '2e9b6a7c-a9fe-466f-9cfb-73f16cce97c2', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Duo', 'serialNumber' => 'SEED-EML-004', 'locationLabel' => 'Floor 1 - East corridor'],
    ['reference' => 'equipment-seed-bulk-12', 'id' => '7069b2cf-5919-43a6-b1bb-cb95bf13e51e', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'camera', 'status' => 'decommissioned', 'brand' => 'Axis', 'model' => 'M3045-V', 'serialNumber' => 'SEED-CAM-003', 'locationLabel' => 'Floor 1 - Legacy storage view'],
    ['reference' => 'equipment-seed-bulk-13', 'id' => '91c2e93c-b292-4347-80a2-6fce4947bbfe', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-003', 'locationLabel' => 'Floor 2 - Open space south'],
    ['reference' => 'equipment-seed-bulk-14', 'id' => '6bd9b036-9a42-47e9-a773-bfdee08a93d1', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5602P', 'serialNumber' => 'SEED-HTD-002', 'locationLabel' => 'Floor 2 - Technical closet'],
    ['reference' => 'equipment-seed-bulk-15', 'id' => '6391d92e-2855-4c24-8c49-b433b38eebed', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'gas_detector', 'status' => 'under_maintenance', 'brand' => 'Drager', 'model' => 'Polytron 8100', 'serialNumber' => 'SEED-GAS-002', 'locationLabel' => 'Floor 2 - Battery room'],
    ['reference' => 'equipment-seed-bulk-16', 'id' => 'fb80f216-17e5-4682-b6d6-fda524897ce7', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 40', 'serialNumber' => 'SEED-ACS-002', 'locationLabel' => 'Floor 2 - Restricted office'],
    ['reference' => 'equipment-seed-bulk-17', 'id' => '9933906a-fa4a-4021-ab4d-cd9e3ed35432', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 5', 'serialNumber' => 'SEED-EXT-003', 'locationLabel' => 'Zone A - Meeting rooms'],
    ['reference' => 'equipment-seed-bulk-18', 'id' => '9387c1af-7b37-4572-997f-ec29e39d9772', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'FSP-951', 'serialNumber' => 'SEED-SMK-004', 'locationLabel' => 'Zone A - Ceiling grid 1'],
    ['reference' => 'equipment-seed-bulk-19', 'id' => '2614e3dd-d70b-4822-b8c1-7983ba2494c0', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'M3086-V', 'serialNumber' => 'SEED-CAM-004', 'locationLabel' => 'Zone A - North corridor'],
    ['reference' => 'equipment-seed-bulk-20', 'id' => 'dd10059c-8e24-4073-b205-fb487a9ac841', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 60', 'serialNumber' => 'SEED-EML-005', 'locationLabel' => 'Zone A - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-21', 'id' => 'db03c891-a55b-4364-a01c-403598c868e0', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'gas_detector', 'status' => 'operational', 'brand' => 'Drager', 'model' => 'Polytron 7000', 'serialNumber' => 'SEED-GAS-003', 'locationLabel' => 'Server Room - Rack row A'],
    ['reference' => 'equipment-seed-bulk-22', 'id' => '73add4ed-9c34-43fe-8c27-b89dc029f380', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 2', 'serialNumber' => 'SEED-EXT-004', 'locationLabel' => 'Server Room - Entrance'],
    ['reference' => 'equipment-seed-bulk-23', 'id' => 'd1ce1a3c-17df-48fd-b89f-7d0801230fe1', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5603P', 'serialNumber' => 'SEED-HTD-003', 'locationLabel' => 'Server Room - Hot aisle'],
    ['reference' => 'equipment-seed-bulk-24', 'id' => 'edf8994d-1dd4-49fe-9910-93a3f959c7fb', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3245-LV', 'serialNumber' => 'SEED-CAM-005', 'locationLabel' => 'Server Room - Door view'],
    ['reference' => 'equipment-seed-bulk-25', 'id' => 'da61f7ee-4110-464b-90cb-ef72c9f49451', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-003', 'locationLabel' => 'Zone B - Ceiling line 1'],
    ['reference' => 'equipment-seed-bulk-26', 'id' => '89c5bf71-70d2-42c2-b031-a86c34b4012e', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Desautel', 'model' => 'P9', 'serialNumber' => 'SEED-EXT-005', 'locationLabel' => 'Zone B - South corridor'],
    ['reference' => 'equipment-seed-bulk-27', 'id' => '19632ff5-13c7-4301-848b-35a5239e27c2', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-003', 'locationLabel' => 'Zone B - Local panel'],
    ['reference' => 'equipment-seed-bulk-28', 'id' => '27894fd1-bbee-4977-8bb5-0f56199c389b', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 45', 'serialNumber' => 'SEED-EML-006', 'locationLabel' => 'Zone B - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-29', 'id' => '8cf822dd-3e81-4856-89b5-59b6edbff672', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => 'System Sensor', 'model' => '5604P', 'serialNumber' => 'SEED-HTD-004', 'locationLabel' => 'Storage Room - Shelf row 1'],
    ['reference' => 'equipment-seed-bulk-30', 'id' => 'a5d61e26-ad2d-4b8c-8d95-f4cb2fb057b9', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-45', 'serialNumber' => 'SEED-SMK-005', 'locationLabel' => 'Storage Room - Ceiling center'],
    ['reference' => 'equipment-seed-bulk-31', 'id' => '7b04a195-196f-4c12-a365-e0ae6d70ea75', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30-S', 'serialNumber' => 'SEED-FDR-003', 'locationLabel' => 'Storage Room - Fire door'],
    ['reference' => 'equipment-seed-bulk-32', 'id' => '0c6afd0c-c88f-43aa-80e9-0b5e557a9a43', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 20', 'serialNumber' => 'SEED-ACS-003', 'locationLabel' => 'Storage Room - Badge reader'],

    // Regional site inventory — gives the Lyon/Marseille/Bordeaux/Lille
    // pins real equipment so their map markers carry a compliance colour
    // instead of rendering as empty sites.
    ['reference' => 'equipment-seed-regional-lyo-01', 'id' => 'b04a80dc-5216-4587-8092-553ebec68b69', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 6', 'serialNumber' => 'SEED-EXT-LYO', 'locationLabel' => 'Lyon Logistics Hub - Main entrance'],
    ['reference' => 'equipment-seed-regional-lyo-02', 'id' => '81418abb-8476-44fb-b371-854f04a5badc', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-LYO', 'locationLabel' => 'Lyon Logistics Hub - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-lyo-03', 'id' => '21d87daf-9b5e-4321-ad3a-7f31331917e5', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-LYO', 'locationLabel' => 'Lyon Logistics Hub - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-lyo-04', 'id' => '5c260e7d-dd20-4929-b078-6b30f4d546cc', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-LYO', 'locationLabel' => 'Lyon Logistics Hub - Reception panel'],
    ['reference' => 'equipment-seed-regional-lyo-05', 'id' => 'b6589cca-57d3-4b45-b071-ad6a874c1e41', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-LYO', 'locationLabel' => 'Lyon Logistics Hub - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-lyo-06', 'id' => 'c14a8b3a-d56d-4a0d-885d-6373e8ad3796', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-LYO', 'locationLabel' => 'Lyon Logistics Hub - Technical corridor'],
    ['reference' => 'equipment-seed-regional-mrs-01', 'id' => 'de9c4bfd-2a04-48e3-9d81-efd3b81e094d', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 6', 'serialNumber' => 'SEED-EXT-MRS', 'locationLabel' => 'Marseille Port Depot - Main entrance'],
    ['reference' => 'equipment-seed-regional-mrs-02', 'id' => 'a09bf00b-016b-470a-9fd2-07cae3efc9a1', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-MRS', 'locationLabel' => 'Marseille Port Depot - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-mrs-03', 'id' => '4e1d8590-d6d9-4cd7-a9fc-f21d12a5c721', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-MRS', 'locationLabel' => 'Marseille Port Depot - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-mrs-04', 'id' => 'a584f5e0-e540-4dc2-8dd7-664dcb391619', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-MRS', 'locationLabel' => 'Marseille Port Depot - Reception panel'],
    ['reference' => 'equipment-seed-regional-mrs-05', 'id' => 'deb9f3b6-d6aa-4a45-882d-0054607d393c', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-MRS', 'locationLabel' => 'Marseille Port Depot - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-mrs-06', 'id' => '356b4639-9cf7-48ed-b7c8-bc35436bb04f', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-MRS', 'locationLabel' => 'Marseille Port Depot - Technical corridor'],
    ['reference' => 'equipment-seed-regional-bod-01', 'id' => '98d7e871-d0f4-4fe7-8c7b-eefb3bc0064b', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 6', 'serialNumber' => 'SEED-EXT-BOD', 'locationLabel' => 'Bordeaux Training Centre - Main entrance'],
    ['reference' => 'equipment-seed-regional-bod-02', 'id' => '1578b266-3f76-4023-9272-17306ed4ea8c', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-BOD', 'locationLabel' => 'Bordeaux Training Centre - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-bod-03', 'id' => 'f042fb8a-66bc-47ce-b04e-f254e3cb2c91', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-BOD', 'locationLabel' => 'Bordeaux Training Centre - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-bod-04', 'id' => '7130c93c-9fe1-4b3e-ae56-db2df067b311', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-BOD', 'locationLabel' => 'Bordeaux Training Centre - Reception panel'],
    ['reference' => 'equipment-seed-regional-bod-05', 'id' => '1dc71c32-28ee-45cf-bb06-c9a310c7c51c', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-BOD', 'locationLabel' => 'Bordeaux Training Centre - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-bod-06', 'id' => '1b8c18a9-e326-41be-abfc-d46ed27853b5', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-BOD', 'locationLabel' => 'Bordeaux Training Centre - Technical corridor'],
    ['reference' => 'equipment-seed-regional-lil-01', 'id' => '0ec476c1-6430-42f2-a48a-186f58414202', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 6', 'serialNumber' => 'SEED-EXT-LIL', 'locationLabel' => 'Lille Distribution Centre - Main entrance'],
    ['reference' => 'equipment-seed-regional-lil-02', 'id' => 'c3ffa423-cf57-4e17-b7ca-88babde6abc8', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-LIL', 'locationLabel' => 'Lille Distribution Centre - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-lil-03', 'id' => 'bcca6a8c-720e-47f5-936a-ae4cee8160e2', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'serialNumber' => 'SEED-EML-LIL', 'locationLabel' => 'Lille Distribution Centre - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-lil-04', 'id' => 'c0282841-c47b-451e-bad2-fe380590b7e4', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-LIL', 'locationLabel' => 'Lille Distribution Centre - Reception panel'],
    ['reference' => 'equipment-seed-regional-lil-05', 'id' => '0c98fff2-cb12-4e23-aaf8-7b90af4bfd2d', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-LIL', 'locationLabel' => 'Lille Distribution Centre - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-lil-06', 'id' => '03a5aea0-f389-4647-aefa-018d12ef5562', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => 'Assa Abloy', 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-LIL', 'locationLabel' => 'Lille Distribution Centre - Technical corridor'],

    // Regional sub-facility inventory — hangs assets off the buildings and
    // zones under each regional site, so the facility tree has equipment at
    // every depth rather than only on the site nodes.
    ['reference' => 'equipment-seed-child-lyo-warehouse', 'id' => 'e13aaf33-6f09-4a45-994a-70cd7940527f', 'facilityReference' => 'facility-seed-lyon-warehouse', 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK310', 'serialNumber' => 'SEED-SPR-LYOW', 'locationLabel' => 'Lyon Warehouse - Rack aisle 4'],
    ['reference' => 'equipment-seed-child-lyo-bay', 'id' => '4d760b50-6f96-417d-9693-bb91317146ac', 'facilityReference' => 'facility-seed-lyon-loading-bay', 'type' => 'gas_detector', 'status' => 'operational', 'brand' => 'Drager', 'model' => 'Polytron 8200', 'serialNumber' => 'SEED-GAS-LYOB', 'locationLabel' => 'Lyon Loading Bay - Forklift charging point'],
    ['reference' => 'equipment-seed-child-mrs-warehouse', 'id' => '458756bb-c139-4601-b368-66f169fcb1c8', 'facilityReference' => 'facility-seed-marseille-warehouse', 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 9', 'serialNumber' => 'SEED-EXT-MRSW', 'locationLabel' => 'Marseille Warehouse - Aisle C'],
    ['reference' => 'equipment-seed-child-mrs-cold', 'id' => 'edd2ca82-e30e-4e46-81d0-c50ac1f00357', 'facilityReference' => 'facility-seed-marseille-cold-store', 'type' => 'heat_detector', 'status' => 'under_maintenance', 'brand' => 'System Sensor', 'model' => '5605P', 'serialNumber' => 'SEED-HTD-MRSC', 'locationLabel' => 'Marseille Cold Store - Compressor room'],
    ['reference' => 'equipment-seed-child-bod-block', 'id' => '302b6234-c089-4bc5-ba4b-bf3b98eb0f7c', 'facilityReference' => 'facility-seed-bordeaux-classroom', 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Duo', 'serialNumber' => 'SEED-EML-BODT', 'locationLabel' => 'Bordeaux Training Block - Classroom corridor'],
    ['reference' => 'equipment-seed-child-bod-burn', 'id' => '5690efa0-120a-472a-9114-5c4b126ff6ea', 'facilityReference' => 'facility-seed-bordeaux-burn-room', 'type' => 'smoke_detector', 'status' => 'decommissioned', 'brand' => 'Honeywell', 'model' => 'SD-40', 'serialNumber' => 'SEED-SMK-BODB', 'locationLabel' => 'Bordeaux Burn Room - Legacy ceiling unit'],
    ['reference' => 'equipment-seed-child-lil-depot', 'id' => 'b715e4ca-35ff-4f7e-878b-868205b7231f', 'facilityReference' => 'facility-seed-lille-depot', 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Siemens', 'model' => 'Cerberus Pro FC361', 'serialNumber' => 'SEED-ALP-LILD', 'locationLabel' => 'Lille Depot - Main panel'],
    ['reference' => 'equipment-seed-child-lil-dispatch', 'id' => 'b673e268-c0b5-45d5-a336-fcc446b0f4c3', 'facilityReference' => 'facility-seed-lille-dispatch', 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 40', 'serialNumber' => 'SEED-ACS-LILD', 'locationLabel' => 'Lille Dispatch Zone - Dock badge reader'],
  ];

  /**
   * Organization-wide equipment tags.
   *
   * Beyond the two hand-wired tags below, these give the tag filter more than
   * a binary choice; {@see self::TAGGED_EQUIPMENT_MODULUS} then spreads them
   * across the bulk fleet so no tag is left with a single member.
   *
   * @var list<array{reference: string, id: string, name: string, createdAt: string}>
   */
  public const array ADDITIONAL_TAG_SEEDS = [
    ['reference' => 'equipment-seed-high-traffic-tag', 'id' => '164475c7-e3bb-4ebf-bdab-9ac427acccae', 'name' => 'high-traffic', 'createdAt' => '2026-03-01T09:02:00+00:00'],
    ['reference' => 'equipment-seed-regulatory-tag', 'id' => '56c3c9f7-84e0-423b-878e-79a08df60930', 'name' => 'regulatory-2026', 'createdAt' => '2026-03-01T09:03:00+00:00'],
    ['reference' => 'equipment-seed-vendor-managed-tag', 'id' => '02632c1c-3334-4ad2-b1e6-259f86a3121c', 'name' => 'vendor-managed', 'createdAt' => '2026-03-01T09:04:00+00:00'],
    ['reference' => 'equipment-seed-battery-backed-tag', 'id' => 'f887f098-4e6e-422b-938f-4997540aee5d', 'name' => 'battery-backed', 'createdAt' => '2026-03-01T09:05:00+00:00'],
  ];

  /**
   * Constant EXTRA_REGIONAL_EQUIPMENT_COUNT.
   *
   * Two assets (site + zone) per {@see self::EXTRA_REGIONAL_SLUGS} entry.
   * Public so {@see \Maintenance\Infrastructure\DataFixtures\MaintenanceFixtures}
   * can loop over the same range and give every one of these assets a
   * schedule — every non-decommissioned equipment row is expected to have
   * exactly one, per `MaintenanceFixturesIntegrationTest`.
   *
   * @since 1.2.0
   *
   * @var int
   */
  public const int EXTRA_REGIONAL_EQUIPMENT_COUNT = 24;

  /**
   * Documents attached to a named piece of equipment.
   *
   * @var list<array{
   *   id: string,
   *   equipmentReference: string,
   *   fileName: string,
   *   mimeType: string,
   *   size: int,
   *   label: string,
   *   uploadedAt: string
   * }>
   */
  private const array ATTACHMENT_SEEDS = [
    ['id' => '26ef6cdf-e64a-4ed4-af88-b3a6cbc7910c', 'equipmentReference' => self::EXTINGUISHER_REFERENCE, 'fileName' => 'extinguisher-service-report-2026.pdf', 'mimeType' => 'application/pdf', 'size' => 118_784, 'label' => 'Service report 2026', 'uploadedAt' => '2026-03-16T09:00:00+00:00'],
    ['id' => '39cd3197-5e86-4675-bed2-10f213d402bd', 'equipmentReference' => self::DETECTOR_REFERENCE, 'fileName' => 'smoke-detector-datasheet.pdf', 'mimeType' => 'application/pdf', 'size' => 204_800, 'label' => 'Manufacturer datasheet', 'uploadedAt' => '2026-03-15T10:00:00+00:00'],
    ['id' => 'b070dc38-0889-4055-aa77-2b3e512db450', 'equipmentReference' => self::DETECTOR_REFERENCE, 'fileName' => 'smoke-detector-install-photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 1_048_576, 'label' => 'Installation photo', 'uploadedAt' => '2026-03-15T10:05:00+00:00'],
    ['id' => '4eed6763-9f12-4c01-8dcf-d02f76167fa4', 'equipmentReference' => self::SPRINKLER_REFERENCE, 'fileName' => 'sprinkler-flow-test.pdf', 'mimeType' => 'application/pdf', 'size' => 262_144, 'label' => 'Flow test certificate', 'uploadedAt' => '2026-03-31T09:00:00+00:00'],
    ['id' => 'd961510c-0cff-4769-ac91-eb67338778cb', 'equipmentReference' => self::ALARM_PANEL_REFERENCE, 'fileName' => 'alarm-panel-wiring-diagram.pdf', 'mimeType' => 'application/pdf', 'size' => 706_560, 'label' => 'Wiring diagram', 'uploadedAt' => '2026-03-31T09:15:00+00:00'],
    ['id' => '17332cb9-9971-425e-a02d-5949ae07a1c2', 'equipmentReference' => self::ALARM_PANEL_REFERENCE, 'fileName' => 'alarm-panel-conformity-certificate.pdf', 'mimeType' => 'application/pdf', 'size' => 96_256, 'label' => 'EN 54 conformity certificate', 'uploadedAt' => '2026-03-31T09:20:00+00:00'],
    ['id' => 'b82f4bba-ed27-4ba1-b8c7-aab5c3f20fac', 'equipmentReference' => self::BUILDING_FIRE_DOOR_REFERENCE, 'fileName' => 'fire-door-closer-quote.pdf', 'mimeType' => 'application/pdf', 'size' => 51_200, 'label' => 'Closer replacement quote', 'uploadedAt' => '2026-04-05T09:00:00+00:00'],
    ['id' => '840039e6-8e4b-4e86-a73d-f4216e523887', 'equipmentReference' => self::FLOOR_TWO_GAS_DETECTOR_REFERENCE, 'fileName' => 'gas-detector-calibration-log.csv', 'mimeType' => 'text/csv', 'size' => 8_192, 'label' => 'Calibration log', 'uploadedAt' => '2026-04-05T09:30:00+00:00'],
    ['id' => 'a7bfc8c9-90d4-4862-86f4-28078a32c5cc', 'equipmentReference' => self::HYDRANT_REFERENCE, 'fileName' => 'hydrant-coupling-invoice.pdf', 'mimeType' => 'application/pdf', 'size' => 40_960, 'label' => 'Coupling replacement invoice', 'uploadedAt' => '2026-02-04T09:00:00+00:00'],
  ];

  /**
   * Completed and in-flight maintenance visits.
   *
   * The equipment detail page's maintenance history reads these rows; a
   * `completedAt` of null is an intervention still on site, which is what the
   * `under_maintenance` assets below are meant to look like.
   *
   * @var list<array{
   *   id: string,
   *   equipmentReference: string,
   *   startedAt: string,
   *   completedAt: ?string,
   *   source: string,
   *   summary: string
   * }>
   */
  private const array MAINTENANCE_LOG_SEEDS = [
    ['id' => 'c360276b-18cc-4b0c-b097-127f2202acde', 'equipmentReference' => self::DETECTOR_REFERENCE, 'startedAt' => '2026-03-21T08:00:00+00:00', 'completedAt' => '2026-03-21T11:30:00+00:00', 'source' => 'status_transition', 'summary' => 'Detector head cleaned and panel loop re-tested.'],
    ['id' => 'f41c8411-3c7e-43f8-a8c4-1fcab0889fd4', 'equipmentReference' => self::SPRINKLER_REFERENCE, 'startedAt' => '2026-02-20T09:00:00+00:00', 'completedAt' => '2026-02-20T16:00:00+00:00', 'source' => 'status_transition', 'summary' => 'Annual flow test and head coverage survey.'],
    ['id' => 'a7dbdf21-842f-46d1-babf-c0aa34188675', 'equipmentReference' => self::ALARM_PANEL_REFERENCE, 'startedAt' => '2026-02-25T13:00:00+00:00', 'completedAt' => '2026-03-02T10:00:00+00:00', 'source' => 'status_transition', 'summary' => 'Event log archived and buffer reset.'],
    ['id' => '120a4d77-98eb-4e24-9d7c-fe6389ec2ba8', 'equipmentReference' => self::HEAT_DETECTOR_REFERENCE, 'startedAt' => '2026-03-18T08:00:00+00:00', 'completedAt' => '2026-03-25T10:00:00+00:00', 'source' => 'status_transition', 'summary' => 'Aging sensor replaced under warranty.'],
    ['id' => 'd3bbc7e1-b67b-4a0c-a9a2-b9b70b83aea1', 'equipmentReference' => self::HYDRANT_REFERENCE, 'startedAt' => '2026-01-20T08:00:00+00:00', 'completedAt' => '2026-02-03T11:00:00+00:00', 'source' => 'status_transition', 'summary' => 'Worn coupling thread replaced by a certified technician.'],
    ['id' => '5e5e56b2-17fd-4076-a29c-e7ac5af03201', 'equipmentReference' => self::BUILDING_FIRE_DOOR_REFERENCE, 'startedAt' => '2026-04-06T08:00:00+00:00', 'completedAt' => null, 'source' => 'status_transition', 'summary' => 'Door closer tension adjustment in progress.'],
    ['id' => '6fbc3542-64b7-4440-96be-de5736a47887', 'equipmentReference' => self::SITE_EMERGENCY_LIGHTING_REFERENCE, 'startedAt' => '2026-03-01T08:00:00+00:00', 'completedAt' => '2026-03-01T09:30:00+00:00', 'source' => 'status_transition', 'summary' => 'Battery autonomy test, 1h30 confirmed.'],
    ['id' => '44e8d5b4-7f99-49b2-931f-12a135ab7da5', 'equipmentReference' => self::FLOOR_TWO_GAS_DETECTOR_REFERENCE, 'startedAt' => '2026-04-07T08:00:00+00:00', 'completedAt' => null, 'source' => 'status_transition', 'summary' => 'Awaiting replacement sensor from the vendor.'],
  ];

  /**
   * Constant TAGGED_EQUIPMENT_MODULUS.
   *
   * Every nth bulk asset picks up a tag, cycling through the tag catalogue by
   * index. Small enough that each tag ends up with a handful of members, large
   * enough that the tag filter still meaningfully narrows the list.
   *
   * @var int
   */
  private const int TAGGED_EQUIPMENT_MODULUS = 3;

  /**
   * Constant EXTRA_REGIONAL_SLUGS.
   *
   * The twelve cities `FacilityFixtures::REGIONAL_SITE_SEEDS` added purely
   * for pagination volume. Without equipment here their map pins would
   * render without a compliance colour, unlike every other regional site —
   * see the "Regional site inventory" comment on `ADDITIONAL_EQUIPMENT_SEEDS`.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array EXTRA_REGIONAL_SLUGS = ['toulouse', 'nantes', 'strasbourg', 'montpellier', 'rennes', 'reims', 'le-havre', 'saint-etienne', 'toulon', 'grenoble', 'dijon', 'angers'];

  /**
   * Constant EXTRA_REGIONAL_EQUIPMENT_KIT.
   *
   * Cycled by index across {@see self::EXTRA_REGIONAL_SLUGS} so the extra
   * cities are not all identical fire-extinguisher clones.
   *
   * @since 1.2.0
   *
   * @var list<array{type: string, brand: string, model: string, status: string}>
   */
  private const array EXTRA_REGIONAL_EQUIPMENT_KIT = [
    ['type' => 'fire_extinguisher', 'brand' => 'Sicli', 'model' => 'Pro 6', 'status' => 'operational'],
    ['type' => 'smoke_detector', 'brand' => 'Honeywell', 'model' => 'SD-46', 'status' => 'operational'],
    ['type' => 'emergency_lighting', 'brand' => 'Legrand', 'model' => 'BAES Eco 45', 'status' => 'operational'],
    ['type' => 'sprinkler', 'brand' => 'Viking', 'model' => 'VK305', 'status' => 'operational'],
    ['type' => 'fire_alarm_panel', 'brand' => 'Notifier', 'model' => 'NFS-320', 'status' => 'under_maintenance'],
    ['type' => 'gas_detector', 'brand' => 'Drager', 'model' => 'Polytron 7000', 'status' => 'operational'],
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
    $criticalTag->createdAt = SeedTimeline::at('2026-03-01T09:00:00+00:00');
    $manager->persist($criticalTag);
    $this->addReference(self::CRITICAL_TAG_REFERENCE, $criticalTag);

    $inspectedTag = new TagRecord();
    $inspectedTag->id = '33333333-3333-4333-8333-333333333332';
    $inspectedTag->organization = $organization;
    $inspectedTag->name = 'annually-inspected';
    $inspectedTag->createdAt = SeedTimeline::at('2026-03-01T09:01:00+00:00');
    $manager->persist($inspectedTag);
    $this->addReference(self::INSPECTED_TAG_REFERENCE, $inspectedTag);

    $tagCatalogue = [$criticalTag, $inspectedTag];
    foreach (self::ADDITIONAL_TAG_SEEDS as $tagSeed) {
      $tag = new TagRecord();
      $tag->id = $tagSeed['id'];
      $tag->organization = $organization;
      $tag->name = $tagSeed['name'];
      $tag->createdAt = SeedTimeline::at($tagSeed['createdAt']);
      $manager->persist($tag);
      $this->addReference($tagSeed['reference'], $tag);
      $tagCatalogue[] = $tag;
    }

    $extinguisher = $this->createEquipment(
      id: '33333333-3333-4333-8333-333333333333',
      organization: $organization,
      facilityId: $zone->id,
      type: EquipmentType::FIRE_EXTINGUISHER->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-06T08:00:00+00:00'),
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
      createdAt: SeedTimeline::at('2026-03-15T08:00:00+00:00'),
      brand: 'Honeywell',
      model: 'SD-42',
      serialNumber: 'SEED-SMK-001',
      locationLabel: 'Server Room - Ceiling',
    );
    $manager->persist($detector);
    $this->addReference(self::DETECTOR_REFERENCE, $detector);

    $hydrant = $this->createEquipment(
      id: 'f0edc890-3d4e-4d96-97b9-f6e30c135cdd',
      organization: $organization,
      facilityId: null,
      type: EquipmentType::HYDRANT->value,
      status: EquipmentStatus::IN_STOCK->value,
      createdAt: SeedTimeline::at('2026-03-28T08:00:00+00:00'),
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
    $attachment->id = '024d32e2-03ae-44de-b741-afd725933d87';
    $attachment->equipment = $extinguisher;
    $attachment->fileName = 'manufacturer-spec.pdf';
    $attachment->storagePath = '/fixtures/equipment/extinguisher/manufacturer-spec.pdf';
    $attachment->mimeType = 'application/pdf';
    $attachment->size = 245760;
    $attachment->label = 'Manufacturer specification';
    $attachment->uploadedAt = SeedTimeline::at('2026-03-06T10:00:00+00:00');
    $manager->persist($attachment);

    $maintenanceLog = new EquipmentMaintenanceLogRecord();
    $maintenanceLog->id = 'bc84ff64-36fe-4714-b9d0-21594ac410d7';
    $maintenanceLog->equipment = $extinguisher;
    $maintenanceLog->organizationId = OrganizationFixtures::ORGANIZATION_ID;
    $maintenanceLog->startedAt = SeedTimeline::at('2026-03-15T08:00:00+00:00');
    $maintenanceLog->completedAt = SeedTimeline::at('2026-03-15T12:00:00+00:00');
    $manager->persist($maintenanceLog);

    $sprinkler = $this->createEquipment(
      id: '4cd977b8-c8e8-4af2-a808-fc6a0ac5197a',
      organization: $organization,
      facilityId: $zoneB->id,
      type: EquipmentType::SPRINKLER->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-30T09:00:00+00:00'),
      brand: 'Viking',
      model: 'VK301',
      serialNumber: 'SEED-SPR-001',
      locationLabel: 'Zone B - Ceiling',
    );
    $manager->persist($sprinkler);
    $this->addReference(self::SPRINKLER_REFERENCE, $sprinkler);

    $alarmPanel = $this->createEquipment(
      id: 'b5760e02-1b39-47f2-a151-ad4ed479f06e',
      organization: $organization,
      facilityId: $zoneB->id,
      type: EquipmentType::FIRE_ALARM_PANEL->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-30T09:10:00+00:00'),
      brand: 'Notifier',
      model: 'NFS2-3030',
      serialNumber: 'SEED-ALP-001',
      locationLabel: 'Zone B - Wall Panel',
    );
    $manager->persist($alarmPanel);
    $this->addReference(self::ALARM_PANEL_REFERENCE, $alarmPanel);

    $heatDetector = $this->createEquipment(
      id: '895ea2b3-3dc8-4ced-af74-eec65dc7e6e3',
      organization: $organization,
      facilityId: $storageRoom->id,
      type: EquipmentType::HEAT_DETECTOR->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-30T09:20:00+00:00'),
      brand: 'System Sensor',
      model: '5601P',
      serialNumber: 'SEED-HTD-001',
      locationLabel: 'Storage Room - Ceiling',
    );
    $manager->persist($heatDetector);
    $this->addReference(self::HEAT_DETECTOR_REFERENCE, $heatDetector);

    $siteEmergencyLighting = $this->createEquipment(
      id: '73c39c5f-f54a-436a-a003-93ba0fbbc112',
      organization: $organization,
      facilityId: $site->id,
      type: EquipmentType::EMERGENCY_LIGHTING->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-31T08:00:00+00:00'),
      brand: 'Legrand',
      model: 'BAES Eco 45',
      serialNumber: 'SEED-EML-001',
      locationLabel: 'Paris Headquarters - Main entrance',
    );
    $manager->persist($siteEmergencyLighting);
    $this->addReference(self::SITE_EMERGENCY_LIGHTING_REFERENCE, $siteEmergencyLighting);

    $buildingFireDoor = $this->createEquipment(
      id: '35c65476-8096-4d02-89c2-ec5fa57523b8',
      organization: $organization,
      facilityId: $building->id,
      type: EquipmentType::FIRE_DOOR->value,
      status: EquipmentStatus::UNDER_MAINTENANCE->value,
      createdAt: SeedTimeline::at('2026-03-31T08:10:00+00:00'),
      brand: 'Assa Abloy',
      model: 'EI60',
      serialNumber: 'SEED-FDR-001',
      locationLabel: 'Main Building - Stairwell A',
    );
    $manager->persist($buildingFireDoor);
    $this->addReference(self::BUILDING_FIRE_DOOR_REFERENCE, $buildingFireDoor);

    $floorOneCamera = $this->createEquipment(
      id: '83fe07fe-488f-4d99-80fb-e04efe6af8db',
      organization: $organization,
      facilityId: $floorOne->id,
      type: EquipmentType::CAMERA->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-31T08:20:00+00:00'),
      brand: 'Axis',
      model: 'M3085-V',
      serialNumber: 'SEED-CAM-001',
      locationLabel: 'Floor 1 - Elevator lobby',
    );
    $manager->persist($floorOneCamera);
    $this->addReference(self::FLOOR_ONE_CAMERA_REFERENCE, $floorOneCamera);

    $floorTwoGasDetector = $this->createEquipment(
      id: '5f1ddf5e-6d7a-487e-9c3f-f9151f287d45',
      organization: $organization,
      facilityId: $floorTwo->id,
      type: EquipmentType::GAS_DETECTOR->value,
      status: EquipmentStatus::OPERATIONAL->value,
      createdAt: SeedTimeline::at('2026-03-31T08:30:00+00:00'),
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

    foreach (FacilityFixtures::REGIONAL_SITE_SEEDS as $regionalSeed) {
      /** @var FacilityRecord $regionalSite */
      $regionalSite = $this->getReference($regionalSeed['reference'], FacilityRecord::class);
      $facilitiesByReference[$regionalSeed['reference']] = $regionalSite;
    }

    foreach (FacilityFixtures::REGIONAL_CHILD_SEEDS as $childSeed) {
      /** @var FacilityRecord $regionalChild */
      $regionalChild = $this->getReference($childSeed['reference'], FacilityRecord::class);
      $facilitiesByReference[$childSeed['reference']] = $regionalChild;
    }

    $equipmentByReference = [
      self::EXTINGUISHER_REFERENCE => $extinguisher,
      self::DETECTOR_REFERENCE => $detector,
      self::HYDRANT_REFERENCE => $hydrant,
      self::SPRINKLER_REFERENCE => $sprinkler,
      self::ALARM_PANEL_REFERENCE => $alarmPanel,
      self::HEAT_DETECTOR_REFERENCE => $heatDetector,
      self::SITE_EMERGENCY_LIGHTING_REFERENCE => $siteEmergencyLighting,
      self::BUILDING_FIRE_DOOR_REFERENCE => $buildingFireDoor,
      self::FLOOR_ONE_CAMERA_REFERENCE => $floorOneCamera,
      self::FLOOR_TWO_GAS_DETECTOR_REFERENCE => $floorTwoGasDetector,
    ];

    foreach (self::ADDITIONAL_EQUIPMENT_SEEDS as $index => $seed) {
      $facility = $facilitiesByReference[$seed['facilityReference']];
      $createdAt = SeedTimeline::at(sprintf('2026-04-%02dT08:%02d:00+00:00', 5 + intdiv($index, 4), ($index % 4) * 10));
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
      $equipmentByReference[$seed['reference']] = $equipment;

      if (0 === $index % self::TAGGED_EQUIPMENT_MODULUS) {
        $link = new EquipmentTagRecord();
        $link->equipment = $equipment;
        $link->tag = $tagCatalogue[intdiv($index, self::TAGGED_EQUIPMENT_MODULUS) % count($tagCatalogue)];
        $manager->persist($link);
      }
    }

    $extraRegionalIndex = 0;
    foreach (self::EXTRA_REGIONAL_SLUGS as $slug) {
      foreach (['site', 'zone'] as $depth) {
        $facility = $facilitiesByReference[sprintf('facility-seed-%s-%s', $slug, $depth)];
        $kit = self::EXTRA_REGIONAL_EQUIPMENT_KIT[$extraRegionalIndex % count(self::EXTRA_REGIONAL_EQUIPMENT_KIT)];
        $createdAt = SeedTimeline::at(sprintf('2026-03-%02dT10:%02d:00+00:00', 13 + intdiv($extraRegionalIndex, 2), ($extraRegionalIndex % 2) * 30));

        $extraEquipment = $this->createEquipment(
          id: SeedUuid::from(sprintf('equipment-extra-regional:%d', $extraRegionalIndex)),
          organization: $organization,
          facilityId: $facility->id,
          type: $kit['type'],
          status: $kit['status'],
          createdAt: $createdAt,
          brand: $kit['brand'],
          model: $kit['model'],
          serialNumber: sprintf('SEED-EXR-%02d', $extraRegionalIndex + 1),
          locationLabel: sprintf('%s %s - %s', ucfirst(str_replace('-', ' ', $slug)), 'site' === $depth ? 'Site' : 'Zone', 'site' === $depth ? 'Main entrance' : 'Operations area'),
        );
        $manager->persist($extraEquipment);
        $this->addReference(self::extraRegionalEquipmentReference($extraRegionalIndex), $extraEquipment);
        ++$extraRegionalIndex;
      }
    }

    foreach (self::ATTACHMENT_SEEDS as $seed) {
      $equipmentAttachment = new EquipmentAttachmentRecord();
      $equipmentAttachment->id = $seed['id'];
      $equipmentAttachment->equipment = $equipmentByReference[$seed['equipmentReference']];
      $equipmentAttachment->fileName = $seed['fileName'];
      $equipmentAttachment->storagePath = sprintf('/fixtures/equipment/%s/%s', $seed['equipmentReference'], $seed['fileName']);
      $equipmentAttachment->mimeType = $seed['mimeType'];
      $equipmentAttachment->size = $seed['size'];
      $equipmentAttachment->label = $seed['label'];
      $equipmentAttachment->uploadedAt = SeedTimeline::at($seed['uploadedAt']);
      $manager->persist($equipmentAttachment);
    }

    foreach (self::MAINTENANCE_LOG_SEEDS as $seed) {
      $log = new EquipmentMaintenanceLogRecord();
      $log->id = $seed['id'];
      $log->equipment = $equipmentByReference[$seed['equipmentReference']];
      $log->organizationId = OrganizationFixtures::ORGANIZATION_ID;
      $log->startedAt = SeedTimeline::at($seed['startedAt']);
      $log->completedAt = null === $seed['completedAt'] ? null : SeedTimeline::at($seed['completedAt']);
      $log->source = $seed['source'];
      $log->summary = $seed['summary'];
      $manager->persist($log);
    }

    $manager->flush();
  }

  /**
   * Method extraRegionalEquipmentReference.
   *
   * @since 1.2.0
   *
   * @param int $index the extra regional equipment index, `0` to `EXTRA_REGIONAL_EQUIPMENT_COUNT - 1`
   *
   * @return string the fixture reference name
   */
  public static function extraRegionalEquipmentReference(int $index): string
  {
    return sprintf('equipment-seed-extra-regional-%02d', $index);
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
