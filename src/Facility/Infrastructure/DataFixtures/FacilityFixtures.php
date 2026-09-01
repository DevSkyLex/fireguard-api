<?php

declare(strict_types=1);

namespace Facility\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityStatus, FacilityType};
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Domain\Attachment\StoragePathScheme;
use Shared\Infrastructure\DataFixtures\SeedTimeline;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_string;
use function mkdir;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class FacilityFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  /**
   * The root site's identifier, paired with the literal its creation date is
   * authored on.
   *
   * Published together so a test reading an already-seeded database can
   * recover the whole-day shift {@see SeedTimeline} applied when the rows were
   * written, instead of recomputing it from the clock of a later run — see
   * `App\Tests\E2E\SeededFixturesFlowTest::seedShiftDays()`.
   */
  public const string SITE_ID = '22222222-2222-4222-8222-222222222221';

  public const string SITE_CREATED_AT = '2026-03-03T08:00:00+00:00';

  public const string SITE_REFERENCE = 'facility-seed-site';

  public const string BUILDING_REFERENCE = 'facility-seed-building';

  public const string FLOOR_ONE_REFERENCE = 'facility-seed-floor-one';

  public const string FLOOR_TWO_REFERENCE = 'facility-seed-floor-two';

  /**
   * Floor 1's primary floor plan. Named as a constant because the floor and
   * every room drawn on it must point at the *same* attachment: the building
   * model only accepts a floor's own outline when it is expressed in that
   * floor's primary-plan coordinate space.
   */
  public const string FLOOR_ONE_PLAN_ID = 'cf54f1c7-fc3c-4082-87c2-c4b061f5ebb4';

  /**
   * Floor 2's primary floor plan. See {@see FLOOR_ONE_PLAN_ID}.
   */
  public const string FLOOR_TWO_PLAN_ID = 'b7d1f0a5-5c3e-4a19-9f26-1d0c4e88a730';

  public const string ZONE_REFERENCE = 'facility-seed-zone';

  public const string ZONE_B_REFERENCE = 'facility-seed-zone-b';

  public const string AREA_REFERENCE = 'facility-seed-area';

  public const string STORAGE_ROOM_REFERENCE = 'facility-seed-storage-room';

  public const string LYON_SITE_REFERENCE = 'facility-seed-lyon-site';

  public const string MARSEILLE_SITE_REFERENCE = 'facility-seed-marseille-site';

  public const string BORDEAUX_SITE_REFERENCE = 'facility-seed-bordeaux-site';

  public const string LILLE_SITE_REFERENCE = 'facility-seed-lille-site';

  /**
   * Regional sites, one per French city, so the compliance map renders a
   * spread of pins instead of a single Paris cluster. Every entry carries
   * coordinates on purpose: an ungeolocated facility is invisible on the map.
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   name: string,
   *   code: string,
   *   address: string,
   *   city: string,
   *   latitude: float,
   *   longitude: float,
   *   createdAt: string
   * }>
   */
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

  /**
   * Sub-hierarchy hung under each regional site.
   *
   * Without it a regional site is a bare pin: its equipment all sits directly
   * on the site node, so the facility tree has nothing to expand and the
   * "equipment by building/zone" breakdowns collapse to a single bucket.
   * Every entry names its parent by reference, and the list is ordered
   * parents-first so a single pass can resolve them.
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   parentReference: string,
   *   type: string,
   *   name: string,
   *   code: string,
   *   createdAt: string
   * }>
   */
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

  /**
   * Documents and floor plans pinned to a facility.
   *
   * Site plans and certificates are what the facility detail page's document
   * tab shows; with none seeded the tab renders permanently empty.
   *
   * A seed carrying `assetFile` is a **floor plan**: its bytes are written to
   * storage from `assets/`, it becomes its facility's primary plan, and it
   * carries the pixel dimensions every normalized polygon is relative to.
   * Without at least one, the plan viewer, the outline editor and the 3D
   * building view have nothing to render at all.
   *
   * @var list<array{
   *   id: string,
   *   facilityReference: string,
   *   fileName: string,
   *   mimeType: string,
   *   size: int,
   *   label: string,
   *   uploadedAt: string,
   *   assetFile: string|null,
   *   imageWidth: int|null,
   *   imageHeight: int|null
   * }>
   */
  private const array ATTACHMENT_SEEDS = [
    ['id' => '12ecfe97-2aa2-4abf-95e1-e2e5a896523a', 'facilityReference' => self::SITE_REFERENCE, 'fileName' => 'paris-site-plan.pdf', 'mimeType' => 'application/pdf', 'size' => 1_248_576, 'label' => 'Site plan', 'uploadedAt' => '2026-03-03T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => '39ef47f3-cfd1-469c-9bd5-4f38633f2554', 'facilityReference' => self::SITE_REFERENCE, 'fileName' => 'paris-fire-safety-certificate.pdf', 'mimeType' => 'application/pdf', 'size' => 312_480, 'label' => 'Fire safety certificate 2026', 'uploadedAt' => '2026-03-03T09:10:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => 'e55da023-82f1-4b2c-b337-916986abfc22', 'facilityReference' => self::BUILDING_REFERENCE, 'fileName' => 'main-building-evacuation-plan.pdf', 'mimeType' => 'application/pdf', 'size' => 856_320, 'label' => 'Evacuation plan', 'uploadedAt' => '2026-03-08T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => self::FLOOR_ONE_PLAN_ID, 'facilityReference' => self::FLOOR_ONE_REFERENCE, 'fileName' => 'floor-1-layout.svg', 'mimeType' => 'image/svg+xml', 'size' => 0, 'label' => 'Floor 1 layout', 'uploadedAt' => '2026-03-12T09:00:00+00:00', 'assetFile' => 'floor-1-layout.svg', 'imageWidth' => 2400, 'imageHeight' => 1600],
    ['id' => self::FLOOR_TWO_PLAN_ID, 'facilityReference' => self::FLOOR_TWO_REFERENCE, 'fileName' => 'floor-2-layout.svg', 'mimeType' => 'image/svg+xml', 'size' => 0, 'label' => 'Floor 2 layout', 'uploadedAt' => '2026-03-16T09:00:00+00:00', 'assetFile' => 'floor-2-layout.svg', 'imageWidth' => 2400, 'imageHeight' => 1600],
    ['id' => '45834581-2392-4d22-8699-a41eb5411b32', 'facilityReference' => self::AREA_REFERENCE, 'fileName' => 'server-room-suppression-spec.pdf', 'mimeType' => 'application/pdf', 'size' => 421_888, 'label' => 'Suppression system specification', 'uploadedAt' => '2026-03-29T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => 'dfebc35b-f432-4fec-82b7-b09409335d57', 'facilityReference' => self::LYON_SITE_REFERENCE, 'fileName' => 'lyon-site-plan.pdf', 'mimeType' => 'application/pdf', 'size' => 987_136, 'label' => 'Site plan', 'uploadedAt' => '2026-03-09T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => '7c4091fb-0fb7-4ab0-b279-54dd686e9a66', 'facilityReference' => self::MARSEILLE_SITE_REFERENCE, 'fileName' => 'marseille-port-permit.pdf', 'mimeType' => 'application/pdf', 'size' => 204_800, 'label' => 'Port authority permit', 'uploadedAt' => '2026-03-10T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => '6e57d89f-b049-4ceb-b931-43c1aa038277', 'facilityReference' => self::BORDEAUX_SITE_REFERENCE, 'fileName' => 'bordeaux-training-programme.pdf', 'mimeType' => 'application/pdf', 'size' => 158_720, 'label' => 'Annual training programme', 'uploadedAt' => '2026-03-11T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
    ['id' => '512b75e9-79dd-4beb-b228-f08a63b30584', 'facilityReference' => self::LILLE_SITE_REFERENCE, 'fileName' => 'lille-sprinkler-as-built.pdf', 'mimeType' => 'application/pdf', 'size' => 1_572_864, 'label' => 'Sprinkler as-built drawings', 'uploadedAt' => '2026-03-12T09:00:00+00:00', 'assetFile' => null, 'imageWidth' => null, 'imageHeight' => null],
  ];

  public static function getGroups(): array
  {
    return ['facility', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [OrganizationFixtures::class];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);

    $site = $this->createFacility(
      id: self::SITE_ID,
      organization: $organization,
      parentFacility: null,
      type: FacilityType::SITE->value,
      name: 'Paris Headquarters',
      code: 'SITE-PAR',
      createdAt: SeedTimeline::at(self::SITE_CREATED_AT),
      address: '12 Rue des Pompiers, Paris',
      metadata: ['city' => 'Paris', 'country' => 'FR'],
      latitude: 48.8566,
      longitude: 2.3522,
    );
    $this->addReference(self::SITE_REFERENCE, $site);
    $manager->persist($site);

    $building = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222222',
      organization: $organization,
      parentFacility: $site,
      type: FacilityType::BUILDING->value,
      name: 'Main Building',
      code: 'BLD-MAIN',
      createdAt: SeedTimeline::at('2026-03-08T08:05:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['usage' => 'office', 'city' => 'Paris'],
      latitude: 48.8570,
      longitude: 2.3527,
    );
    $this->addReference(self::BUILDING_REFERENCE, $building);
    $manager->persist($building);

    $floorOne = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222223',
      organization: $organization,
      parentFacility: $building,
      type: FacilityType::FLOOR->value,
      name: 'Floor 1',
      code: 'FL-01',
      createdAt: SeedTimeline::at('2026-03-12T08:10:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['level' => '1', 'city' => 'Paris'],
      latitude: 48.8572,
      longitude: 2.3531,
      levelIndex: 0,
      planGeometry: ['attachmentId' => self::FLOOR_ONE_PLAN_ID, 'points' => [[0.05, 0.08], [0.95, 0.08], [0.95, 0.92], [0.05, 0.92]]],
    );
    $this->addReference(self::FLOOR_ONE_REFERENCE, $floorOne);
    $manager->persist($floorOne);

    $floorTwo = $this->createFacility(
      id: '99ea3fc6-1ee9-4e77-a59a-a2b20f1e295c',
      organization: $organization,
      parentFacility: $building,
      type: FacilityType::FLOOR->value,
      name: 'Floor 2',
      code: 'FL-02',
      createdAt: SeedTimeline::at('2026-03-16T08:11:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['level' => '2', 'city' => 'Paris'],
      latitude: 48.8574,
      longitude: 2.3535,
      levelIndex: 1,
      planGeometry: ['attachmentId' => self::FLOOR_TWO_PLAN_ID, 'points' => [[0.08, 0.10], [0.92, 0.10], [0.92, 0.90], [0.08, 0.90]]],
    );
    $this->addReference(self::FLOOR_TWO_REFERENCE, $floorTwo);
    $manager->persist($floorTwo);

    $zone = $this->createFacility(
      id: '824f43e2-ffd0-4b23-a8e0-9a55152aef65',
      organization: $organization,
      parentFacility: $floorOne,
      type: FacilityType::ZONE->value,
      name: 'Zone A',
      code: 'ZN-A',
      createdAt: SeedTimeline::at('2026-03-22T08:15:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['sector' => 'north', 'city' => 'Paris'],
      latitude: 48.8576,
      longitude: 2.3539,
      planGeometry: ['attachmentId' => self::FLOOR_ONE_PLAN_ID, 'points' => [[0.10, 0.14], [0.46, 0.14], [0.46, 0.55], [0.10, 0.55]]],
    );
    $this->addReference(self::ZONE_REFERENCE, $zone);
    $manager->persist($zone);

    $area = $this->createFacility(
      id: 'ea8f2946-d377-4ffb-90a5-c15f859cb388',
      organization: $organization,
      parentFacility: $zone,
      type: FacilityType::AREA->value,
      name: 'Server Room',
      code: 'AR-SRV',
      createdAt: SeedTimeline::at('2026-03-29T08:20:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['restricted' => true, 'city' => 'Paris'],
      latitude: 48.8578,
      longitude: 2.3543,
      planGeometry: ['attachmentId' => self::FLOOR_ONE_PLAN_ID, 'points' => [[0.14, 0.20], [0.40, 0.20], [0.40, 0.48], [0.14, 0.48]]],
    );
    $this->addReference(self::AREA_REFERENCE, $area);
    $manager->persist($area);

    $zoneB = $this->createFacility(
      id: '3648ba52-4ef4-45c5-8613-caa7db756bb4',
      organization: $organization,
      parentFacility: $floorTwo,
      type: FacilityType::ZONE->value,
      name: 'Zone B',
      code: 'ZN-B',
      createdAt: SeedTimeline::at('2026-03-30T08:00:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['sector' => 'south', 'city' => 'Paris'],
      latitude: 48.8580,
      longitude: 2.3547,
      planGeometry: ['attachmentId' => self::FLOOR_TWO_PLAN_ID, 'points' => [[0.52, 0.16], [0.88, 0.16], [0.88, 0.60], [0.52, 0.60]]],
    );
    $this->addReference(self::ZONE_B_REFERENCE, $zoneB);
    $manager->persist($zoneB);

    $storageRoom = $this->createFacility(
      id: '0c99caad-984e-4589-9131-f11c27ca39d3',
      organization: $organization,
      parentFacility: $zoneB,
      type: FacilityType::AREA->value,
      name: 'Storage Room',
      code: 'AR-STR',
      createdAt: SeedTimeline::at('2026-03-30T08:05:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['restricted' => false, 'city' => 'Paris'],
      latitude: 48.8582,
      longitude: 2.3551,
      planGeometry: ['attachmentId' => self::FLOOR_TWO_PLAN_ID, 'points' => [[0.56, 0.22], [0.82, 0.22], [0.82, 0.54], [0.56, 0.54]]],
    );
    $this->addReference(self::STORAGE_ROOM_REFERENCE, $storageRoom);
    $manager->persist($storageRoom);

    $facilitiesByReference = [
      self::SITE_REFERENCE => $site,
      self::BUILDING_REFERENCE => $building,
      self::FLOOR_ONE_REFERENCE => $floorOne,
      self::FLOOR_TWO_REFERENCE => $floorTwo,
      self::ZONE_REFERENCE => $zone,
      self::AREA_REFERENCE => $area,
      self::ZONE_B_REFERENCE => $zoneB,
      self::STORAGE_ROOM_REFERENCE => $storageRoom,
    ];

    foreach (self::REGIONAL_SITE_SEEDS as $seed) {
      $regionalSite = $this->createFacility(
        id: $seed['id'],
        organization: $organization,
        parentFacility: null,
        type: FacilityType::SITE->value,
        name: $seed['name'],
        code: $seed['code'],
        createdAt: SeedTimeline::at($seed['createdAt']),
        address: $seed['address'],
        metadata: ['city' => $seed['city'], 'country' => 'FR'],
        latitude: $seed['latitude'],
        longitude: $seed['longitude'],
      );
      $this->addReference($seed['reference'], $regionalSite);
      $manager->persist($regionalSite);
      $facilitiesByReference[$seed['reference']] = $regionalSite;
    }

    // Ordered parents-first, so every parent reference is already resolved.
    foreach (self::REGIONAL_CHILD_SEEDS as $seed) {
      $parent = $facilitiesByReference[$seed['parentReference']];

      $child = $this->createFacility(
        id: $seed['id'],
        organization: $organization,
        parentFacility: $parent,
        type: $seed['type'],
        name: $seed['name'],
        code: $seed['code'],
        createdAt: SeedTimeline::at($seed['createdAt']),
        address: $parent->address,
        metadata: ['city' => $parent->metadata['city'] ?? null, 'country' => 'FR'],
        latitude: null === $parent->latitude ? null : $parent->latitude + 0.0006,
        longitude: null === $parent->longitude ? null : $parent->longitude + 0.0006,
      );
      $this->addReference($seed['reference'], $child);
      $manager->persist($child);
      $facilitiesByReference[$seed['reference']] = $child;
    }

    // A decommissioned annex: the only archived row in the tree, so the
    // status filter and the "hidden by default" listing behaviour have
    // something to act on.
    $archivedAnnex = $this->createFacility(
      id: '68402941-5767-4d8b-a373-fe15467a5649',
      organization: $organization,
      parentFacility: $site,
      type: FacilityType::BUILDING->value,
      name: 'Old Annex',
      code: 'BLD-ANNEX',
      createdAt: SeedTimeline::at('2026-03-08T08:00:00+00:00'),
      address: '14 Rue des Pompiers, 75011 Paris',
      metadata: ['city' => 'Paris', 'usage' => 'storage', 'decommissionedReason' => 'demolished'],
      latitude: 48.8568,
      longitude: 2.3519,
      status: FacilityStatus::ARCHIVED->value,
    );
    $this->addReference(self::ARCHIVED_ANNEX_REFERENCE, $archivedAnnex);
    $manager->persist($archivedAnnex);
    $facilitiesByReference[self::ARCHIVED_ANNEX_REFERENCE] = $archivedAnnex;

    foreach (self::ATTACHMENT_SEEDS as $seed) {
      $facility = $facilitiesByReference[$seed['facilityReference']];

      $attachment = new FacilityAttachmentRecord();
      $attachment->id = $seed['id'];
      $attachment->facility = $facility;
      $attachment->fileName = $seed['fileName'];
      $attachment->mimeType = $seed['mimeType'];
      $attachment->label = $seed['label'];
      $attachment->uploadedAt = SeedTimeline::at($seed['uploadedAt']);

      $assetFile = $seed['assetFile'];

      if (null === $assetFile) {
        // A document seed: nothing reads its bytes, so it keeps a placeholder
        // path and no file is written. Only floor plans are actually fetched
        // by the UI, and only those are worth the disk.
        $attachment->storagePath = sprintf('/fixtures/facility/%s/%s', $seed['facilityReference'], $seed['fileName']);
        $attachment->size = $seed['size'];
        $manager->persist($attachment);

        continue;
      }

      // A floor plan: the viewer downloads it, so it needs real bytes at the
      // real path the download responder resolves — not the placeholder above.
      $contents = (string) file_get_contents(dirname(__DIR__) . '/DataFixtures/assets/' . $assetFile);
      $storagePath = StoragePathScheme::build('facility', $facility->id, $attachment->id, $seed['fileName']);
      $this->writeSeedFile($storagePath, $contents);

      $attachment->storagePath = $storagePath;
      $attachment->size = strlen($contents);
      $attachment->kind = AttachmentKind::FLOOR_PLAN->value;
      $attachment->isPrimaryPlan = true;
      $attachment->imageWidth = $seed['imageWidth'];
      $attachment->imageHeight = $seed['imageHeight'];
      $manager->persist($attachment);
    }

    $manager->flush();
  }

  /**
   * Method writeSeedFile.
   *
   * Writes a seeded attachment's bytes straight onto the local storage disk.
   *
   * Deliberately not `FileStoragePort`: Doctrine's fixture `Loader` resolves a
   * declared dependency with a bare `new $class()` (`Loader::createFixture`),
   * so a fixture that any other fixture depends on can never take a
   * constructor argument — it would be a fatal error the moment the loader
   * reaches it. Reading the same `STORAGE_DSN` the Flysystem factory reads
   * keeps the two in step without a container.
   *
   * Only the `local://` scheme is handled, which is the only one seeding ever
   * runs against; anything else is left alone rather than guessed at.
   *
   * @since 1.0.0
   *
   * @param string $storagePath the key the download responder will resolve
   * @param string $contents the file's bytes
   */
  private function writeSeedFile(string $storagePath, string $contents): void
  {
    $dsn = $_ENV['STORAGE_DSN'] ?? '';

    if (!is_string($dsn) || !str_starts_with($dsn, 'local://')) {
      return;
    }

    $root = dirname(__DIR__, 4) . '/' . substr($dsn, strlen('local://'));
    $target = $root . '/' . $storagePath;

    @mkdir(dirname($target), 0o775, true);
    file_put_contents($target, $contents);
  }

  /**
   * @param array<string, mixed> $metadata
   * @param array{attachmentId: string, points: list<array{float, float}>}|null $planGeometry
   */
  private function createFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parentFacility,
    string $type,
    string $name,
    ?string $code,
    DateTimeImmutable $createdAt,
    ?string $address = null,
    array $metadata = [],
    ?float $latitude = null,
    ?float $longitude = null,
    string $status = FacilityStatus::ACTIVE->value,
    ?int $levelIndex = null,
    ?array $planGeometry = null,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parentFacility;
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = $code;
    $facility->status = $status;
    $facility->address = $address;
    $facility->latitude = $latitude;
    $facility->longitude = $longitude;
    $facility->metadata = $metadata;
    $facility->levelIndex = $levelIndex;
    $facility->planGeometry = $planGeometry;
    $facility->createdAt = $createdAt;
    $facility->updatedAt = $createdAt;

    return $facility;
  }
}
