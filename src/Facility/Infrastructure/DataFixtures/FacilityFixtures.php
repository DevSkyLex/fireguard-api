<?php

declare(strict_types=1);

namespace Facility\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Facility\Domain\ValueObject\{FacilityStatus, FacilityType};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

final class FacilityFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  public const string SITE_REFERENCE = 'facility-seed-site';

  public const string BUILDING_REFERENCE = 'facility-seed-building';

  public const string FLOOR_ONE_REFERENCE = 'facility-seed-floor-one';

  public const string FLOOR_TWO_REFERENCE = 'facility-seed-floor-two';

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
      'id' => '22222222-2222-4222-8222-222222222229',
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
      'id' => '22222222-2222-4222-8222-22222222222a',
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
      'id' => '22222222-2222-4222-8222-22222222222b',
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
      'id' => '22222222-2222-4222-8222-22222222222c',
      'name' => 'Lille Distribution Centre',
      'code' => 'SITE-LIL',
      'address' => '120 Rue du Ballon, 59000 Lille',
      'city' => 'Lille',
      'latitude' => 50.6431,
      'longitude' => 3.0752,
      'createdAt' => '2026-03-07T08:00:00+00:00',
    ],
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
      id: '22222222-2222-4222-8222-222222222221',
      organization: $organization,
      parentFacility: null,
      type: FacilityType::SITE->value,
      name: 'Paris Headquarters',
      code: 'SITE-PAR',
      createdAt: new DateTimeImmutable('2026-03-03T08:00:00+00:00'),
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
      createdAt: new DateTimeImmutable('2026-03-08T08:05:00+00:00'),
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
      createdAt: new DateTimeImmutable('2026-03-12T08:10:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['level' => '1', 'city' => 'Paris'],
      latitude: 48.8572,
      longitude: 2.3531,
    );
    $this->addReference(self::FLOOR_ONE_REFERENCE, $floorOne);
    $manager->persist($floorOne);

    $floorTwo = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222224',
      organization: $organization,
      parentFacility: $building,
      type: FacilityType::FLOOR->value,
      name: 'Floor 2',
      code: 'FL-02',
      createdAt: new DateTimeImmutable('2026-03-16T08:11:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['level' => '2', 'city' => 'Paris'],
      latitude: 48.8574,
      longitude: 2.3535,
    );
    $this->addReference(self::FLOOR_TWO_REFERENCE, $floorTwo);
    $manager->persist($floorTwo);

    $zone = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222225',
      organization: $organization,
      parentFacility: $floorOne,
      type: FacilityType::ZONE->value,
      name: 'Zone A',
      code: 'ZN-A',
      createdAt: new DateTimeImmutable('2026-03-22T08:15:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['sector' => 'north', 'city' => 'Paris'],
      latitude: 48.8576,
      longitude: 2.3539,
    );
    $this->addReference(self::ZONE_REFERENCE, $zone);
    $manager->persist($zone);

    $area = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222226',
      organization: $organization,
      parentFacility: $zone,
      type: FacilityType::AREA->value,
      name: 'Server Room',
      code: 'AR-SRV',
      createdAt: new DateTimeImmutable('2026-03-29T08:20:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['restricted' => true, 'city' => 'Paris'],
      latitude: 48.8578,
      longitude: 2.3543,
    );
    $this->addReference(self::AREA_REFERENCE, $area);
    $manager->persist($area);

    $zoneB = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222227',
      organization: $organization,
      parentFacility: $floorTwo,
      type: FacilityType::ZONE->value,
      name: 'Zone B',
      code: 'ZN-B',
      createdAt: new DateTimeImmutable('2026-03-30T08:00:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['sector' => 'south', 'city' => 'Paris'],
      latitude: 48.8580,
      longitude: 2.3547,
    );
    $this->addReference(self::ZONE_B_REFERENCE, $zoneB);
    $manager->persist($zoneB);

    $storageRoom = $this->createFacility(
      id: '22222222-2222-4222-8222-222222222228',
      organization: $organization,
      parentFacility: $zoneB,
      type: FacilityType::AREA->value,
      name: 'Storage Room',
      code: 'AR-STR',
      createdAt: new DateTimeImmutable('2026-03-30T08:05:00+00:00'),
      address: '12 Rue des Pompiers, 75011 Paris',
      metadata: ['restricted' => false, 'city' => 'Paris'],
      latitude: 48.8582,
      longitude: 2.3551,
    );
    $this->addReference(self::STORAGE_ROOM_REFERENCE, $storageRoom);
    $manager->persist($storageRoom);

    foreach (self::REGIONAL_SITE_SEEDS as $seed) {
      $regionalSite = $this->createFacility(
        id: $seed['id'],
        organization: $organization,
        parentFacility: null,
        type: FacilityType::SITE->value,
        name: $seed['name'],
        code: $seed['code'],
        createdAt: new DateTimeImmutable($seed['createdAt']),
        address: $seed['address'],
        metadata: ['city' => $seed['city'], 'country' => 'FR'],
        latitude: $seed['latitude'],
        longitude: $seed['longitude'],
      );
      $this->addReference($seed['reference'], $regionalSite);
      $manager->persist($regionalSite);
    }

    $manager->flush();
  }

  /**
   * @param array<string, mixed> $metadata
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
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parentFacility;
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = $code;
    $facility->status = FacilityStatus::ACTIVE->value;
    $facility->address = $address;
    $facility->latitude = $latitude;
    $facility->longitude = $longitude;
    $facility->metadata = $metadata;
    $facility->createdAt = $createdAt;
    $facility->updatedAt = $createdAt;

    return $facility;
  }
}
