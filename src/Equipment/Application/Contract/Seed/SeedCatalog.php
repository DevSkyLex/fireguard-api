<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Seed;

use Facility\Application\Contract\Seed\SeedCatalog as FacilityFixtures;

use function sprintf;

/**
 * Class SeedCatalog
 *
 * Publishes deterministic demonstration identifiers and metadata required by other seed owners.
 *
 * @category Contract
 */
final class SeedCatalog
{
  public const string EXTINGUISHER_REFERENCE = 'equipment-seed-extinguisher';

  public const string DETECTOR_REFERENCE = 'equipment-seed-detector';

  public const string HYDRANT_REFERENCE = 'equipment-seed-hydrant';

  public const string SPRINKLER_REFERENCE = 'equipment-seed-sprinkler';

  public const string ALARM_PANEL_REFERENCE = 'equipment-seed-alarm-panel';

  public const string HEAT_DETECTOR_REFERENCE = 'equipment-seed-heat-detector';

  public const string SITE_EMERGENCY_LIGHTING_REFERENCE = 'equipment-seed-site-emergency-lighting';

  public const string BUILDING_FIRE_DOOR_REFERENCE = 'equipment-seed-building-fire-door';

  public const string FLOOR_ONE_CAMERA_REFERENCE = 'equipment-seed-floor-one-camera';

  public const string FLOOR_TWO_GAS_DETECTOR_REFERENCE = 'equipment-seed-floor-two-gas-detector';

  public const array ADDITIONAL_EQUIPMENT_SEEDS = [
    ['reference' => 'equipment-seed-bulk-01', 'id' => '0cab488f-5780-4a8f-9d50-b14d665fba73', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => self::BAES_ECO_45_MODEL, 'serialNumber' => 'SEED-EML-002', 'locationLabel' => 'Paris Headquarters - West exit'],
    ['reference' => 'equipment-seed-bulk-02', 'id' => '33333333-3333-4333-8333-333333333341', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3265-LV', 'serialNumber' => 'SEED-CAM-002', 'locationLabel' => 'Paris Headquarters - Parking gate'],
    ['reference' => 'equipment-seed-bulk-03', 'id' => '33333333-3333-4333-8333-333333333342', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'hydrant', 'status' => 'under_maintenance', 'brand' => 'Desautel', 'model' => 'H-100', 'serialNumber' => 'SEED-HYD-002', 'locationLabel' => 'Paris Headquarters - Courtyard'],
    ['reference' => 'equipment-seed-bulk-04', 'id' => '33333333-3333-4333-8333-333333333343', 'facilityReference' => FacilityFixtures::SITE_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Edge EVO', 'serialNumber' => 'SEED-ACS-001', 'locationLabel' => 'Paris Headquarters - Reception access'],
    ['reference' => 'equipment-seed-bulk-05', 'id' => '33333333-3333-4333-8333-333333333344', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Siemens', 'model' => 'Cerberus Pro FC922', 'serialNumber' => 'SEED-ALP-002', 'locationLabel' => 'Main Building - Security desk'],
    ['reference' => 'equipment-seed-bulk-06', 'id' => '33333333-3333-4333-8333-333333333345', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'fire_door', 'status' => 'operational', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-002', 'locationLabel' => 'Main Building - Stairwell B'],
    ['reference' => 'equipment-seed-bulk-07', 'id' => '33333333-3333-4333-8333-333333333346', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 30', 'serialNumber' => 'SEED-EML-003', 'locationLabel' => 'Main Building - Central corridor'],
    ['reference' => 'equipment-seed-bulk-08', 'id' => '33333333-3333-4333-8333-333333333347', 'facilityReference' => FacilityFixtures::BUILDING_REFERENCE, 'type' => 'sprinkler', 'status' => 'under_maintenance', 'brand' => 'Viking', 'model' => 'VK302', 'serialNumber' => 'SEED-SPR-002', 'locationLabel' => 'Main Building - Atrium'],
    ['reference' => 'equipment-seed-bulk-09', 'id' => 'df71b23a-1e76-4fdf-a8d7-bbbd9c6a41bb', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-002', 'locationLabel' => 'Floor 1 - Open space north'],
    ['reference' => 'equipment-seed-bulk-10', 'id' => '3fee3091-9151-4c32-bc17-69a988cc1bb1', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 9', 'serialNumber' => 'SEED-EXT-002', 'locationLabel' => 'Floor 1 - Copy room'],
    ['reference' => 'equipment-seed-bulk-11', 'id' => '2e9b6a7c-a9fe-466f-9cfb-73f16cce97c2', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Duo', 'serialNumber' => 'SEED-EML-004', 'locationLabel' => 'Floor 1 - East corridor'],
    ['reference' => 'equipment-seed-bulk-12', 'id' => '7069b2cf-5919-43a6-b1bb-cb95bf13e51e', 'facilityReference' => FacilityFixtures::FLOOR_ONE_REFERENCE, 'type' => 'camera', 'status' => 'decommissioned', 'brand' => 'Axis', 'model' => 'M3045-V', 'serialNumber' => 'SEED-CAM-003', 'locationLabel' => 'Floor 1 - Legacy storage view'],
    ['reference' => 'equipment-seed-bulk-13', 'id' => '91c2e93c-b292-4347-80a2-6fce4947bbfe', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-44', 'serialNumber' => 'SEED-SMK-003', 'locationLabel' => 'Floor 2 - Open space south'],
    ['reference' => 'equipment-seed-bulk-14', 'id' => '6bd9b036-9a42-47e9-a773-bfdee08a93d1', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => self::SYSTEM_SENSOR_BRAND, 'model' => '5602P', 'serialNumber' => 'SEED-HTD-002', 'locationLabel' => 'Floor 2 - Technical closet'],
    ['reference' => 'equipment-seed-bulk-15', 'id' => '6391d92e-2855-4c24-8c49-b433b38eebed', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'gas_detector', 'status' => 'under_maintenance', 'brand' => 'Drager', 'model' => 'Polytron 8100', 'serialNumber' => 'SEED-GAS-002', 'locationLabel' => 'Floor 2 - Battery room'],
    ['reference' => 'equipment-seed-bulk-16', 'id' => 'fb80f216-17e5-4682-b6d6-fda524897ce7', 'facilityReference' => FacilityFixtures::FLOOR_TWO_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 40', 'serialNumber' => 'SEED-ACS-002', 'locationLabel' => 'Floor 2 - Restricted office'],
    ['reference' => 'equipment-seed-bulk-17', 'id' => '9933906a-fa4a-4021-ab4d-cd9e3ed35432', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 5', 'serialNumber' => 'SEED-EXT-003', 'locationLabel' => 'Zone A - Meeting rooms'],
    ['reference' => 'equipment-seed-bulk-18', 'id' => '9387c1af-7b37-4572-997f-ec29e39d9772', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'FSP-951', 'serialNumber' => 'SEED-SMK-004', 'locationLabel' => 'Zone A - Ceiling grid 1'],
    ['reference' => 'equipment-seed-bulk-19', 'id' => '2614e3dd-d70b-4822-b8c1-7983ba2494c0', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'M3086-V', 'serialNumber' => 'SEED-CAM-004', 'locationLabel' => 'Zone A - North corridor'],
    ['reference' => 'equipment-seed-bulk-20', 'id' => 'dd10059c-8e24-4073-b205-fb487a9ac841', 'facilityReference' => FacilityFixtures::ZONE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Eco 60', 'serialNumber' => 'SEED-EML-005', 'locationLabel' => 'Zone A - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-21', 'id' => 'db03c891-a55b-4364-a01c-403598c868e0', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'gas_detector', 'status' => 'operational', 'brand' => 'Drager', 'model' => self::POLYTRON_7000_MODEL, 'serialNumber' => 'SEED-GAS-003', 'locationLabel' => 'Server Room - Rack row A'],
    ['reference' => 'equipment-seed-bulk-22', 'id' => '73add4ed-9c34-43fe-8c27-b89dc029f380', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'CO2 2', 'serialNumber' => 'SEED-EXT-004', 'locationLabel' => 'Server Room - Entrance'],
    ['reference' => 'equipment-seed-bulk-23', 'id' => 'd1ce1a3c-17df-48fd-b89f-7d0801230fe1', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => self::SYSTEM_SENSOR_BRAND, 'model' => '5603P', 'serialNumber' => 'SEED-HTD-003', 'locationLabel' => 'Server Room - Hot aisle'],
    ['reference' => 'equipment-seed-bulk-24', 'id' => 'edf8994d-1dd4-49fe-9910-93a3f959c7fb', 'facilityReference' => FacilityFixtures::AREA_REFERENCE, 'type' => 'camera', 'status' => 'operational', 'brand' => 'Axis', 'model' => 'P3245-LV', 'serialNumber' => 'SEED-CAM-005', 'locationLabel' => 'Server Room - Door view'],
    ['reference' => 'equipment-seed-bulk-25', 'id' => 'da61f7ee-4110-464b-90cb-ef72c9f49451', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-003', 'locationLabel' => 'Zone B - Ceiling line 1'],
    ['reference' => 'equipment-seed-bulk-26', 'id' => '89c5bf71-70d2-42c2-b031-a86c34b4012e', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Desautel', 'model' => 'P9', 'serialNumber' => 'SEED-EXT-005', 'locationLabel' => 'Zone B - South corridor'],
    ['reference' => 'equipment-seed-bulk-27', 'id' => '19632ff5-13c7-4301-848b-35a5239e27c2', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-003', 'locationLabel' => 'Zone B - Local panel'],
    ['reference' => 'equipment-seed-bulk-28', 'id' => '27894fd1-bbee-4977-8bb5-0f56199c389b', 'facilityReference' => FacilityFixtures::ZONE_B_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Cooper', 'model' => 'GuideLed 45', 'serialNumber' => 'SEED-EML-006', 'locationLabel' => 'Zone B - Emergency exit'],
    ['reference' => 'equipment-seed-bulk-29', 'id' => '8cf822dd-3e81-4856-89b5-59b6edbff672', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'heat_detector', 'status' => 'operational', 'brand' => self::SYSTEM_SENSOR_BRAND, 'model' => '5604P', 'serialNumber' => 'SEED-HTD-004', 'locationLabel' => 'Storage Room - Shelf row 1'],
    ['reference' => 'equipment-seed-bulk-30', 'id' => 'a5d61e26-ad2d-4b8c-8d95-f4cb2fb057b9', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-45', 'serialNumber' => 'SEED-SMK-005', 'locationLabel' => 'Storage Room - Ceiling center'],
    ['reference' => 'equipment-seed-bulk-31', 'id' => '7b04a195-196f-4c12-a365-e0ae6d70ea75', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30-S', 'serialNumber' => 'SEED-FDR-003', 'locationLabel' => 'Storage Room - Fire door'],
    ['reference' => 'equipment-seed-bulk-32', 'id' => '0c6afd0c-c88f-43aa-80e9-0b5e557a9a43', 'facilityReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE, 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 20', 'serialNumber' => 'SEED-ACS-003', 'locationLabel' => 'Storage Room - Badge reader'],

    // Regional site inventory — gives the Lyon/Marseille/Bordeaux/Lille
    // pins real equipment so their map markers carry a compliance colour
    // instead of rendering as empty sites.
    ['reference' => 'equipment-seed-regional-lyo-01', 'id' => 'b04a80dc-5216-4587-8092-553ebec68b69', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => self::PRO_6_MODEL, 'serialNumber' => 'SEED-EXT-LYO', 'locationLabel' => 'Lyon Logistics Hub - Main entrance'],
    ['reference' => 'equipment-seed-regional-lyo-02', 'id' => '81418abb-8476-44fb-b371-854f04a5badc', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-LYO', 'locationLabel' => 'Lyon Logistics Hub - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-lyo-03', 'id' => '21d87daf-9b5e-4321-ad3a-7f31331917e5', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => self::BAES_ECO_45_MODEL, 'serialNumber' => 'SEED-EML-LYO', 'locationLabel' => 'Lyon Logistics Hub - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-lyo-04', 'id' => '5c260e7d-dd20-4929-b078-6b30f4d546cc', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-LYO', 'locationLabel' => 'Lyon Logistics Hub - Reception panel'],
    ['reference' => 'equipment-seed-regional-lyo-05', 'id' => 'b6589cca-57d3-4b45-b071-ad6a874c1e41', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-LYO', 'locationLabel' => 'Lyon Logistics Hub - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-lyo-06', 'id' => 'c14a8b3a-d56d-4a0d-885d-6373e8ad3796', 'facilityReference' => FacilityFixtures::LYON_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-LYO', 'locationLabel' => 'Lyon Logistics Hub - Technical corridor'],
    ['reference' => 'equipment-seed-regional-mrs-01', 'id' => 'de9c4bfd-2a04-48e3-9d81-efd3b81e094d', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => self::PRO_6_MODEL, 'serialNumber' => 'SEED-EXT-MRS', 'locationLabel' => 'Marseille Port Depot - Main entrance'],
    ['reference' => 'equipment-seed-regional-mrs-02', 'id' => 'a09bf00b-016b-470a-9fd2-07cae3efc9a1', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-MRS', 'locationLabel' => 'Marseille Port Depot - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-mrs-03', 'id' => '4e1d8590-d6d9-4cd7-a9fc-f21d12a5c721', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => self::BAES_ECO_45_MODEL, 'serialNumber' => 'SEED-EML-MRS', 'locationLabel' => 'Marseille Port Depot - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-mrs-04', 'id' => 'a584f5e0-e540-4dc2-8dd7-664dcb391619', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-MRS', 'locationLabel' => 'Marseille Port Depot - Reception panel'],
    ['reference' => 'equipment-seed-regional-mrs-05', 'id' => 'deb9f3b6-d6aa-4a45-882d-0054607d393c', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-MRS', 'locationLabel' => 'Marseille Port Depot - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-mrs-06', 'id' => '356b4639-9cf7-48ed-b7c8-bc35436bb04f', 'facilityReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-MRS', 'locationLabel' => 'Marseille Port Depot - Technical corridor'],
    ['reference' => 'equipment-seed-regional-bod-01', 'id' => '98d7e871-d0f4-4fe7-8c7b-eefb3bc0064b', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => self::PRO_6_MODEL, 'serialNumber' => 'SEED-EXT-BOD', 'locationLabel' => 'Bordeaux Training Centre - Main entrance'],
    ['reference' => 'equipment-seed-regional-bod-02', 'id' => '1578b266-3f76-4023-9272-17306ed4ea8c', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-BOD', 'locationLabel' => 'Bordeaux Training Centre - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-bod-03', 'id' => 'f042fb8a-66bc-47ce-b04e-f254e3cb2c91', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => self::BAES_ECO_45_MODEL, 'serialNumber' => 'SEED-EML-BOD', 'locationLabel' => 'Bordeaux Training Centre - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-bod-04', 'id' => '7130c93c-9fe1-4b3e-ae56-db2df067b311', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-BOD', 'locationLabel' => 'Bordeaux Training Centre - Reception panel'],
    ['reference' => 'equipment-seed-regional-bod-05', 'id' => '1dc71c32-28ee-45cf-bb06-c9a310c7c51c', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-BOD', 'locationLabel' => 'Bordeaux Training Centre - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-bod-06', 'id' => '1b8c18a9-e326-41be-abfc-d46ed27853b5', 'facilityReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-BOD', 'locationLabel' => 'Bordeaux Training Centre - Technical corridor'],
    ['reference' => 'equipment-seed-regional-lil-01', 'id' => '0ec476c1-6430-42f2-a48a-186f58414202', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => self::PRO_6_MODEL, 'serialNumber' => 'SEED-EXT-LIL', 'locationLabel' => 'Lille Distribution Centre - Main entrance'],
    ['reference' => 'equipment-seed-regional-lil-02', 'id' => 'c3ffa423-cf57-4e17-b7ca-88babde6abc8', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'smoke_detector', 'status' => 'operational', 'brand' => 'Honeywell', 'model' => 'SD-46', 'serialNumber' => 'SEED-SMK-LIL', 'locationLabel' => 'Lille Distribution Centre - Warehouse ceiling'],
    ['reference' => 'equipment-seed-regional-lil-03', 'id' => 'bcca6a8c-720e-47f5-936a-ae4cee8160e2', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => self::BAES_ECO_45_MODEL, 'serialNumber' => 'SEED-EML-LIL', 'locationLabel' => 'Lille Distribution Centre - Loading bay exit'],
    ['reference' => 'equipment-seed-regional-lil-04', 'id' => 'c0282841-c47b-451e-bad2-fe380590b7e4', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Notifier', 'model' => 'NFS-320', 'serialNumber' => 'SEED-ALP-LIL', 'locationLabel' => 'Lille Distribution Centre - Reception panel'],
    ['reference' => 'equipment-seed-regional-lil-05', 'id' => '0c98fff2-cb12-4e23-aaf8-7b90af4bfd2d', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK305', 'serialNumber' => 'SEED-SPR-LIL', 'locationLabel' => 'Lille Distribution Centre - Storage aisle 2'],
    ['reference' => 'equipment-seed-regional-lil-06', 'id' => '03a5aea0-f389-4647-aefa-018d12ef5562', 'facilityReference' => FacilityFixtures::LILLE_SITE_REFERENCE, 'type' => 'fire_door', 'status' => 'under_maintenance', 'brand' => self::ASSA_ABLOY_BRAND, 'model' => 'EI30', 'serialNumber' => 'SEED-FDR-LIL', 'locationLabel' => 'Lille Distribution Centre - Technical corridor'],

    // Regional sub-facility inventory — hangs assets off the buildings and
    // zones under each regional site, so the facility tree has equipment at
    // every depth rather than only on the site nodes.
    ['reference' => 'equipment-seed-child-lyo-warehouse', 'id' => 'e13aaf33-6f09-4a45-994a-70cd7940527f', 'facilityReference' => 'facility-seed-lyon-warehouse', 'type' => 'sprinkler', 'status' => 'operational', 'brand' => 'Viking', 'model' => 'VK310', 'serialNumber' => 'SEED-SPR-LYOW', 'locationLabel' => 'Lyon Warehouse - Rack aisle 4'],
    ['reference' => 'equipment-seed-child-lyo-bay', 'id' => '4d760b50-6f96-417d-9693-bb91317146ac', 'facilityReference' => 'facility-seed-lyon-loading-bay', 'type' => 'gas_detector', 'status' => 'operational', 'brand' => 'Drager', 'model' => 'Polytron 8200', 'serialNumber' => 'SEED-GAS-LYOB', 'locationLabel' => 'Lyon Loading Bay - Forklift charging point'],
    ['reference' => 'equipment-seed-child-mrs-warehouse', 'id' => '458756bb-c139-4601-b368-66f169fcb1c8', 'facilityReference' => 'facility-seed-marseille-warehouse', 'type' => 'fire_extinguisher', 'status' => 'operational', 'brand' => 'Sicli', 'model' => 'Pro 9', 'serialNumber' => 'SEED-EXT-MRSW', 'locationLabel' => 'Marseille Warehouse - Aisle C'],
    ['reference' => 'equipment-seed-child-mrs-cold', 'id' => 'edd2ca82-e30e-4e46-81d0-c50ac1f00357', 'facilityReference' => 'facility-seed-marseille-cold-store', 'type' => 'heat_detector', 'status' => 'under_maintenance', 'brand' => self::SYSTEM_SENSOR_BRAND, 'model' => '5605P', 'serialNumber' => 'SEED-HTD-MRSC', 'locationLabel' => 'Marseille Cold Store - Compressor room'],
    ['reference' => 'equipment-seed-child-bod-block', 'id' => '302b6234-c089-4bc5-ba4b-bf3b98eb0f7c', 'facilityReference' => 'facility-seed-bordeaux-classroom', 'type' => 'emergency_lighting', 'status' => 'operational', 'brand' => 'Legrand', 'model' => 'BAES Duo', 'serialNumber' => 'SEED-EML-BODT', 'locationLabel' => 'Bordeaux Training Block - Classroom corridor'],
    ['reference' => 'equipment-seed-child-bod-burn', 'id' => '5690efa0-120a-472a-9114-5c4b126ff6ea', 'facilityReference' => 'facility-seed-bordeaux-burn-room', 'type' => 'smoke_detector', 'status' => 'decommissioned', 'brand' => 'Honeywell', 'model' => 'SD-40', 'serialNumber' => 'SEED-SMK-BODB', 'locationLabel' => 'Bordeaux Burn Room - Legacy ceiling unit'],
    ['reference' => 'equipment-seed-child-lil-depot', 'id' => 'b715e4ca-35ff-4f7e-878b-868205b7231f', 'facilityReference' => 'facility-seed-lille-depot', 'type' => 'fire_alarm_panel', 'status' => 'operational', 'brand' => 'Siemens', 'model' => 'Cerberus Pro FC361', 'serialNumber' => 'SEED-ALP-LILD', 'locationLabel' => 'Lille Depot - Main panel'],
    ['reference' => 'equipment-seed-child-lil-dispatch', 'id' => 'b673e268-c0b5-45d5-a336-fcc446b0f4c3', 'facilityReference' => 'facility-seed-lille-dispatch', 'type' => 'access_control', 'status' => 'operational', 'brand' => 'HID', 'model' => 'Signo 40', 'serialNumber' => 'SEED-ACS-LILD', 'locationLabel' => 'Lille Dispatch Zone - Dock badge reader'],
  ];

  public const int EXTRA_REGIONAL_EQUIPMENT_COUNT = 24;

  private const string BAES_ECO_45_MODEL = 'BAES Eco 45';

  private const string ASSA_ABLOY_BRAND = 'Assa Abloy';

  private const string SYSTEM_SENSOR_BRAND = 'System Sensor';

  private const string POLYTRON_7000_MODEL = 'Polytron 7000';

  private const string PRO_6_MODEL = 'Pro 6';

  public static function extraRegionalEquipmentReference(int $index): string
  {
    return sprintf('equipment-seed-extra-regional-%02d', $index);
  }
}
