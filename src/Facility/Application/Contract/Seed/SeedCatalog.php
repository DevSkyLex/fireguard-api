<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Seed;

/**
 * Class SeedCatalog
 *
 * Publishes deterministic demonstration identifiers and metadata required by other seed owners.
 *
 * @category Contract
 */
final class SeedCatalog
{
  public const string SITE_REFERENCE = 'facility-seed-site';

  public const string BUILDING_REFERENCE = 'facility-seed-building';

  public const string FLOOR_ONE_REFERENCE = 'facility-seed-floor-one';

  public const string FLOOR_TWO_REFERENCE = 'facility-seed-floor-two';

  public const string FLOOR_ONE_PLAN_ID = 'cf54f1c7-fc3c-4082-87c2-c4b061f5ebb4';

  public const string ZONE_REFERENCE = 'facility-seed-zone';

  public const string ZONE_B_REFERENCE = 'facility-seed-zone-b';

  public const string AREA_REFERENCE = 'facility-seed-area';

  public const string STORAGE_ROOM_REFERENCE = 'facility-seed-storage-room';

  public const string LYON_SITE_REFERENCE = 'facility-seed-lyon-site';

  public const string MARSEILLE_SITE_REFERENCE = 'facility-seed-marseille-site';

  public const string BORDEAUX_SITE_REFERENCE = 'facility-seed-bordeaux-site';

  public const string LILLE_SITE_REFERENCE = 'facility-seed-lille-site';

  public const array REGIONAL_SITE_SEEDS = [
    [
      'reference' => self::LYON_SITE_REFERENCE,
      'id' => '72017284-dfcf-41ab-8d4e-73c483857635',
      'name' => 'Lyon Logistics Hub',
      'code' => 'SITE-LYO',
      'address' => '48 Quai Rambaud, 69002 Lyon',
      'city' => 'Lyon',
      'latitude' => 45.7402,
      'longitude' => 4.8175,
      'createdAt' => '2026-03-04T08:00:00+00:00',
    ],
    [
      'reference' => self::MARSEILLE_SITE_REFERENCE,
      'id' => '3680cf02-5863-4a52-b2de-a33b6ccc0f5e',
      'name' => 'Marseille Port Depot',
      'code' => 'SITE-MRS',
      'address' => '15 Boulevard de Dunkerque, 13002 Marseille',
      'city' => 'Marseille',
      'latitude' => 43.3047,
      'longitude' => 5.3661,
      'createdAt' => '2026-03-05T08:00:00+00:00',
    ],
    [
      'reference' => self::BORDEAUX_SITE_REFERENCE,
      'id' => 'a5495139-ef90-42ea-89f5-8bfff10556a2',
      'name' => 'Bordeaux Training Centre',
      'code' => 'SITE-BOD',
      'address' => '9 Quai de Bacalan, 33300 Bordeaux',
      'city' => 'Bordeaux',
      'latitude' => 44.8590,
      'longitude' => -0.5540,
      'createdAt' => '2026-03-06T08:00:00+00:00',
    ],
    [
      'reference' => self::LILLE_SITE_REFERENCE,
      'id' => '8ef06db3-db8a-42f2-afa3-b782079f055b',
      'name' => 'Lille Distribution Centre',
      'code' => 'SITE-LIL',
      'address' => '120 Rue du Ballon, 59000 Lille',
      'city' => 'Lille',
      'latitude' => 50.6431,
      'longitude' => 3.0752,
      'createdAt' => '2026-03-07T08:00:00+00:00',
    ],
    // Twelve more cities, kept flat (site + main building + zone rather than
    // Paris's deeper tree) purely to push the facility list past 50 rows so
    // its pagination actually has more than one page to page through.
    ['reference' => 'facility-seed-toulouse-site', 'id' => '3d4ec723-d18e-41c9-86dc-b00262ecb0bd', 'name' => 'Toulouse Distribution Hub', 'code' => 'SITE-TLS', 'address' => '3 Quai de Tounis, 31000 Toulouse', 'city' => 'Toulouse', 'latitude' => 43.6047, 'longitude' => 1.4442, 'createdAt' => '2026-03-13T08:00:00+00:00'],
    ['reference' => 'facility-seed-nantes-site', 'id' => '8822abd4-7297-4183-b423-a0aaab8a9923', 'name' => 'Nantes Riverside Depot', 'code' => 'SITE-NTE', 'address' => '22 Quai de la Fosse, 44000 Nantes', 'city' => 'Nantes', 'latitude' => 47.2184, 'longitude' => -1.5536, 'createdAt' => '2026-03-14T08:00:00+00:00'],
    ['reference' => 'facility-seed-strasbourg-site', 'id' => '1282e630-cb5d-4765-8f29-f67a131b13b3', 'name' => 'Strasbourg Border Logistics Centre', 'code' => 'SITE-STR', 'address' => '5 Rue du Faubourg National, 67000 Strasbourg', 'city' => 'Strasbourg', 'latitude' => 48.5734, 'longitude' => 7.7521, 'createdAt' => '2026-03-15T08:00:00+00:00'],
    ['reference' => 'facility-seed-montpellier-site', 'id' => '4abd042b-0809-4069-9584-d2761869d7a9', 'name' => 'Montpellier Regional Depot', 'code' => 'SITE-MPL', 'address' => '18 Avenue de Toulouse, 34000 Montpellier', 'city' => 'Montpellier', 'latitude' => 43.6108, 'longitude' => 3.8767, 'createdAt' => '2026-03-16T08:00:00+00:00'],
    ['reference' => 'facility-seed-rennes-site', 'id' => '34cd8728-8f9e-482e-b936-af38ced9b1b0', 'name' => 'Rennes West Distribution Centre', 'code' => 'SITE-RNS', 'address' => '7 Rue de Nantes, 35000 Rennes', 'city' => 'Rennes', 'latitude' => 48.1173, 'longitude' => -1.6778, 'createdAt' => '2026-03-17T08:00:00+00:00'],
    ['reference' => 'facility-seed-reims-site', 'id' => 'c04fc689-5f8c-4853-bbd3-35180c8ada98', 'name' => 'Reims Champagne Depot', 'code' => 'SITE-REI', 'address' => '14 Boulevard Lundy, 51100 Reims', 'city' => 'Reims', 'latitude' => 49.2583, 'longitude' => 4.0317, 'createdAt' => '2026-03-18T08:00:00+00:00'],
    ['reference' => 'facility-seed-le-havre-site', 'id' => '7134c63d-fa25-4281-a3c4-a67f0abbb5ec', 'name' => 'Le Havre Port Terminal', 'code' => 'SITE-LEH', 'address' => '2 Quai de Southampton, 76600 Le Havre', 'city' => 'Le Havre', 'latitude' => 49.4944, 'longitude' => 0.1079, 'createdAt' => '2026-03-19T08:00:00+00:00'],
    ['reference' => 'facility-seed-saint-etienne-site', 'id' => '057ea14f-b57b-4d96-ac1e-fb62c2f2d7a9', 'name' => 'Saint-Etienne Industrial Site', 'code' => 'SITE-SET', 'address' => '9 Rue de la Republique, 42000 Saint-Etienne', 'city' => 'Saint-Etienne', 'latitude' => 45.4397, 'longitude' => 4.3872, 'createdAt' => '2026-03-20T08:00:00+00:00'],
    ['reference' => 'facility-seed-toulon-site', 'id' => '175cb250-7a08-4631-a15c-f29de3e35ff1', 'name' => 'Toulon Naval Support Depot', 'code' => 'SITE-TLN', 'address' => '6 Avenue de la Republique, 83000 Toulon', 'city' => 'Toulon', 'latitude' => 43.1242, 'longitude' => 5.928, 'createdAt' => '2026-03-21T08:00:00+00:00'],
    ['reference' => 'facility-seed-grenoble-site', 'id' => '20777fa3-5c72-444b-ac92-8668c5c82cab', 'name' => 'Grenoble Alpine Logistics Centre', 'code' => 'SITE-GRE', 'address' => '11 Cours Jean Jaures, 38000 Grenoble', 'city' => 'Grenoble', 'latitude' => 45.1885, 'longitude' => 5.7245, 'createdAt' => '2026-03-22T08:00:00+00:00'],
    ['reference' => 'facility-seed-dijon-site', 'id' => '74f68d2e-69d4-4b55-acf7-846ea15e3835', 'name' => 'Dijon Burgundy Depot', 'code' => 'SITE-DIJ', 'address' => '4 Rue de la Liberte, 21000 Dijon', 'city' => 'Dijon', 'latitude' => 47.322, 'longitude' => 5.0415, 'createdAt' => '2026-03-23T08:00:00+00:00'],
    ['reference' => 'facility-seed-angers-site', 'id' => 'e655e57a-9b3b-408e-b752-2d1ec738d48d', 'name' => 'Angers Loire Distribution Centre', 'code' => 'SITE-ANG', 'address' => '16 Rue Plantagenet, 49000 Angers', 'city' => 'Angers', 'latitude' => 47.4784, 'longitude' => -0.5632, 'createdAt' => '2026-03-24T08:00:00+00:00'],
  ];

  public const string ARCHIVED_ANNEX_REFERENCE = 'facility-seed-archived-annex';

  public const array REGIONAL_CHILD_SEEDS = [
    ['reference' => 'facility-seed-lyon-warehouse', 'id' => '0e8dac2b-6182-40fe-8bca-54d81ae8a5d0', 'parentReference' => self::LYON_SITE_REFERENCE, 'type' => 'building', 'name' => 'Lyon Warehouse', 'code' => 'BLD-LYO-W', 'createdAt' => '2026-03-09T08:00:00+00:00'],
    ['reference' => 'facility-seed-lyon-loading-bay', 'id' => '6d9bb41f-1b5f-4db2-adcd-939453295328', 'parentReference' => 'facility-seed-lyon-warehouse', 'type' => 'zone', 'name' => 'Lyon Loading Bay', 'code' => 'ZN-LYO-LB', 'createdAt' => '2026-03-09T08:30:00+00:00'],
    ['reference' => 'facility-seed-marseille-warehouse', 'id' => 'e7b600fa-9b66-435d-8577-2280945081eb', 'parentReference' => self::MARSEILLE_SITE_REFERENCE, 'type' => 'building', 'name' => 'Marseille Warehouse', 'code' => 'BLD-MRS-W', 'createdAt' => '2026-03-10T08:00:00+00:00'],
    ['reference' => 'facility-seed-marseille-cold-store', 'id' => '906f4ff5-4517-4f6f-a882-c6c620ac4cdc', 'parentReference' => 'facility-seed-marseille-warehouse', 'type' => 'area', 'name' => 'Marseille Cold Store', 'code' => 'AR-MRS-CS', 'createdAt' => '2026-03-10T08:30:00+00:00'],
    ['reference' => 'facility-seed-bordeaux-classroom', 'id' => '4a625838-3ae8-442b-8023-294a71cd478d', 'parentReference' => self::BORDEAUX_SITE_REFERENCE, 'type' => 'building', 'name' => 'Bordeaux Training Block', 'code' => 'BLD-BOD-T', 'createdAt' => '2026-03-11T08:00:00+00:00'],
    ['reference' => 'facility-seed-bordeaux-burn-room', 'id' => '643a0546-0598-4c27-b58a-2fa97db698a3', 'parentReference' => 'facility-seed-bordeaux-classroom', 'type' => 'area', 'name' => 'Bordeaux Burn Room', 'code' => 'AR-BOD-BR', 'createdAt' => '2026-03-11T08:30:00+00:00'],
    ['reference' => 'facility-seed-lille-depot', 'id' => '984107ea-86a8-411e-8464-1546a2565cc8', 'parentReference' => self::LILLE_SITE_REFERENCE, 'type' => 'building', 'name' => 'Lille Depot', 'code' => 'BLD-LIL-D', 'createdAt' => '2026-03-12T08:00:00+00:00'],
    ['reference' => 'facility-seed-lille-dispatch', 'id' => '3ff07d7a-e71d-4cd3-9c4f-8eccadfe6b42', 'parentReference' => 'facility-seed-lille-depot', 'type' => 'zone', 'name' => 'Lille Dispatch Zone', 'code' => 'ZN-LIL-DP', 'createdAt' => '2026-03-12T08:30:00+00:00'],
    ['reference' => 'facility-seed-toulouse-building', 'id' => '3d9d5a31-5625-4508-a8b0-261e38670d1f', 'parentReference' => 'facility-seed-toulouse-site', 'type' => 'building', 'name' => 'Toulouse Main Building', 'code' => 'BLD-TLS', 'createdAt' => '2026-03-13T08:30:00+00:00'],
    ['reference' => 'facility-seed-toulouse-zone', 'id' => 'bcbad2e5-40ff-4bae-bc69-da94f0fb87f6', 'parentReference' => 'facility-seed-toulouse-building', 'type' => 'zone', 'name' => 'Toulouse Operations Zone', 'code' => 'ZN-TLS', 'createdAt' => '2026-03-13T09:00:00+00:00'],
    ['reference' => 'facility-seed-nantes-building', 'id' => '97cdc905-56e9-42d0-aa58-70741af864c5', 'parentReference' => 'facility-seed-nantes-site', 'type' => 'building', 'name' => 'Nantes Main Building', 'code' => 'BLD-NTE', 'createdAt' => '2026-03-14T08:30:00+00:00'],
    ['reference' => 'facility-seed-nantes-zone', 'id' => 'd807d824-7df9-43ec-a70b-cf1a124a56e2', 'parentReference' => 'facility-seed-nantes-building', 'type' => 'zone', 'name' => 'Nantes Operations Zone', 'code' => 'ZN-NTE', 'createdAt' => '2026-03-14T09:00:00+00:00'],
    ['reference' => 'facility-seed-strasbourg-building', 'id' => '90cf6d5f-27aa-4d54-a511-bb946de5d632', 'parentReference' => 'facility-seed-strasbourg-site', 'type' => 'building', 'name' => 'Strasbourg Main Building', 'code' => 'BLD-STR', 'createdAt' => '2026-03-15T08:30:00+00:00'],
    ['reference' => 'facility-seed-strasbourg-zone', 'id' => '81d3aa8c-102b-4e25-91cb-742947bd0cc8', 'parentReference' => 'facility-seed-strasbourg-building', 'type' => 'zone', 'name' => 'Strasbourg Operations Zone', 'code' => 'ZN-STR', 'createdAt' => '2026-03-15T09:00:00+00:00'],
    ['reference' => 'facility-seed-montpellier-building', 'id' => '3d3066f8-4ed7-4c56-9918-d44431c8150d', 'parentReference' => 'facility-seed-montpellier-site', 'type' => 'building', 'name' => 'Montpellier Main Building', 'code' => 'BLD-MPL', 'createdAt' => '2026-03-16T08:30:00+00:00'],
    ['reference' => 'facility-seed-montpellier-zone', 'id' => 'b9363bac-4335-40f2-887b-beaa66dee69c', 'parentReference' => 'facility-seed-montpellier-building', 'type' => 'zone', 'name' => 'Montpellier Operations Zone', 'code' => 'ZN-MPL', 'createdAt' => '2026-03-16T09:00:00+00:00'],
    ['reference' => 'facility-seed-rennes-building', 'id' => '3393b67c-0358-42b9-872b-b2bbc11e3a5d', 'parentReference' => 'facility-seed-rennes-site', 'type' => 'building', 'name' => 'Rennes Main Building', 'code' => 'BLD-RNS', 'createdAt' => '2026-03-17T08:30:00+00:00'],
    ['reference' => 'facility-seed-rennes-zone', 'id' => '88e963a3-d27d-458e-9ece-464b3f9a2e6a', 'parentReference' => 'facility-seed-rennes-building', 'type' => 'zone', 'name' => 'Rennes Operations Zone', 'code' => 'ZN-RNS', 'createdAt' => '2026-03-17T09:00:00+00:00'],
    ['reference' => 'facility-seed-reims-building', 'id' => '30660d7b-2a6a-451b-9a82-bde21bd54123', 'parentReference' => 'facility-seed-reims-site', 'type' => 'building', 'name' => 'Reims Main Building', 'code' => 'BLD-REI', 'createdAt' => '2026-03-18T08:30:00+00:00'],
    ['reference' => 'facility-seed-reims-zone', 'id' => '81b6c8e3-efe1-4a65-bc65-00f1cf656a3c', 'parentReference' => 'facility-seed-reims-building', 'type' => 'zone', 'name' => 'Reims Operations Zone', 'code' => 'ZN-REI', 'createdAt' => '2026-03-18T09:00:00+00:00'],
    ['reference' => 'facility-seed-le-havre-building', 'id' => '9acc436c-3f2f-4433-99f1-7ef48cbc4f60', 'parentReference' => 'facility-seed-le-havre-site', 'type' => 'building', 'name' => 'Le Havre Main Building', 'code' => 'BLD-LEH', 'createdAt' => '2026-03-19T08:30:00+00:00'],
    ['reference' => 'facility-seed-le-havre-zone', 'id' => '9651ec27-0e76-49b5-977e-d4b20fefe912', 'parentReference' => 'facility-seed-le-havre-building', 'type' => 'zone', 'name' => 'Le Havre Operations Zone', 'code' => 'ZN-LEH', 'createdAt' => '2026-03-19T09:00:00+00:00'],
    ['reference' => 'facility-seed-saint-etienne-building', 'id' => 'ee10b988-8b39-46d0-9803-5eb2fe51a765', 'parentReference' => 'facility-seed-saint-etienne-site', 'type' => 'building', 'name' => 'Saint-Etienne Main Building', 'code' => 'BLD-SET', 'createdAt' => '2026-03-20T08:30:00+00:00'],
    ['reference' => 'facility-seed-saint-etienne-zone', 'id' => 'a63eab6f-25a9-40d8-9575-dbddf56b235f', 'parentReference' => 'facility-seed-saint-etienne-building', 'type' => 'zone', 'name' => 'Saint-Etienne Operations Zone', 'code' => 'ZN-SET', 'createdAt' => '2026-03-20T09:00:00+00:00'],
    ['reference' => 'facility-seed-toulon-building', 'id' => '16cc75f2-0072-43d2-aac8-d151d68b0735', 'parentReference' => 'facility-seed-toulon-site', 'type' => 'building', 'name' => 'Toulon Main Building', 'code' => 'BLD-TLN', 'createdAt' => '2026-03-21T08:30:00+00:00'],
    ['reference' => 'facility-seed-toulon-zone', 'id' => '0e04bd13-5fd2-40fa-be0b-21adbb3f41f5', 'parentReference' => 'facility-seed-toulon-building', 'type' => 'zone', 'name' => 'Toulon Operations Zone', 'code' => 'ZN-TLN', 'createdAt' => '2026-03-21T09:00:00+00:00'],
    ['reference' => 'facility-seed-grenoble-building', 'id' => 'bec764b5-40d4-4f7d-afd8-cdd6d659f24b', 'parentReference' => 'facility-seed-grenoble-site', 'type' => 'building', 'name' => 'Grenoble Main Building', 'code' => 'BLD-GRE', 'createdAt' => '2026-03-22T08:30:00+00:00'],
    ['reference' => 'facility-seed-grenoble-zone', 'id' => '4d9927e2-c287-4fb4-8f96-259cc3fd24f9', 'parentReference' => 'facility-seed-grenoble-building', 'type' => 'zone', 'name' => 'Grenoble Operations Zone', 'code' => 'ZN-GRE', 'createdAt' => '2026-03-22T09:00:00+00:00'],
    ['reference' => 'facility-seed-dijon-building', 'id' => 'e4c9e675-2532-4e3a-af1d-3e7afde8e55c', 'parentReference' => 'facility-seed-dijon-site', 'type' => 'building', 'name' => 'Dijon Main Building', 'code' => 'BLD-DIJ', 'createdAt' => '2026-03-23T08:30:00+00:00'],
    ['reference' => 'facility-seed-dijon-zone', 'id' => '37572557-7dd6-4b3c-92a8-72d5eab20e32', 'parentReference' => 'facility-seed-dijon-building', 'type' => 'zone', 'name' => 'Dijon Operations Zone', 'code' => 'ZN-DIJ', 'createdAt' => '2026-03-23T09:00:00+00:00'],
    ['reference' => 'facility-seed-angers-building', 'id' => '064ea088-b42f-42f0-b6c4-e0905081ef8d', 'parentReference' => 'facility-seed-angers-site', 'type' => 'building', 'name' => 'Angers Main Building', 'code' => 'BLD-ANG', 'createdAt' => '2026-03-24T08:30:00+00:00'],
    ['reference' => 'facility-seed-angers-zone', 'id' => 'c0763555-28b9-48d2-944e-4b1cac8d036a', 'parentReference' => 'facility-seed-angers-building', 'type' => 'zone', 'name' => 'Angers Operations Zone', 'code' => 'ZN-ANG', 'createdAt' => '2026-03-24T09:00:00+00:00'],
  ];
}
