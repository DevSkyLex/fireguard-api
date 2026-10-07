<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Intervention;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Intervention\InterventionEquipmentSnapshotAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Publication\InterventionFactsScopeTooLarge;
use Intervention\Application\Port\Outbound\InterventionEquipmentSnapshotPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_keys;
use function array_map;
use function range;
use function sprintf;

/**
 * Test InterventionEquipmentSnapshotAdapterTest.
 *
 * Exercises the owning equipment bridge on PostgreSQL, including retained assets and scoped hierarchy identity.
 *
 * @category Adapter Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionEquipmentSnapshotAdapter::class)]
final class InterventionEquipmentSnapshotAdapterTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $manager;
  }

  public function testRetiredEquipmentAndArchivedSitesKeepDeclaredIdentityFromTheirOwner(): void
  {
    $organization = $this->organization(1);
    $customer = new CustomerRecord();
    $customer->id = self::id(10);
    $customer->organizationId = $organization->id;
    $customer->name = 'Archived site customer';
    $customer->contacts = [['name' => 'Private contact', 'email' => 'private@example.test', 'phone' => null, 'role' => null]];
    $customer->createdAt = $customer->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $this->entityManager->persist($customer);
    $site = $this->facility(11, $organization, 'site', null);
    $site->status = 'archived';
    $site->customerId = $customer->id;
    $building = $this->facility(12, $organization, 'building', $site);
    $equipment = $this->equipment(20, $organization, $building->id);
    $equipment->type = 'archived_custom_fire_type';
    $equipment->status = 'retired';
    $equipment->name = 'Old cylinder';
    $equipment->assetCode = 'FIRE-001';
    $equipment->brand = 'Recorded brand';
    $equipment->model = 'Recorded model';
    $equipment->serialNumber = 'SERIAL-001';
    $this->entityManager->flush();
    $snapshots = $this->port()->snapshots($organization->id, [$equipment->id, $equipment->id]);
    self::assertSame([$equipment->id], array_keys($snapshots));
    $snapshot = $snapshots[$equipment->id];
    self::assertSame('Old cylinder', $snapshot->name);
    self::assertSame('FIRE-001', $snapshot->assetReference);
    self::assertSame('archived_custom_fire_type', $snapshot->type);
    self::assertSame('Recorded brand', $snapshot->brand);
    self::assertSame('Recorded model', $snapshot->model);
    self::assertSame('SERIAL-001', $snapshot->serialNumber);
    self::assertSame($building->id, $snapshot->facilityId);
    self::assertSame(['id' => $site->id, 'name' => $site->name], $snapshot->site);
    self::assertSame(['id' => $customer->id, 'name' => $customer->name], $snapshot->customer);
    self::assertSame([$equipment->id], $this->port()->equipmentIdsInFacilities($organization->id, [$site->id, $building->id]));
  }

  public function testMissingDraftAndForeignAssetsAreOmittedAndForeignLocationsCannotLeakIdentity(): void
  {
    $organization = $this->organization(1);
    $foreign = $this->organization(2);
    $site = $this->facility(11, $organization, 'site', null);
    $foreignSite = $this->facility(12, $foreign, 'site', null);
    $valid = $this->equipment(20, $organization, $site->id);
    $draft = $this->equipment(21, $organization, $site->id);
    $draft->recordStatus = 'draft';
    $other = $this->equipment(22, $foreign, $site->id);
    $mislocated = $this->equipment(23, $organization, $foreignSite->id);
    $this->entityManager->flush();
    $port = $this->port();
    $snapshots = $port->snapshots($organization->id, [$other->id, $draft->id, $mislocated->id, self::id(999), $valid->id]);
    self::assertSame([$valid->id, $mislocated->id], array_keys($snapshots));
    self::assertNull($snapshots[$valid->id]->name);
    self::assertNull($snapshots[$valid->id]->assetReference);
    self::assertNull($snapshots[$valid->id]->serialNumber);
    self::assertSame($foreignSite->id, $snapshots[$mislocated->id]->facilityId);
    self::assertNull($snapshots[$mislocated->id]->site);
    self::assertNull($snapshots[$mislocated->id]->customer);
    self::assertSame([$valid->id], $port->equipmentIdsInFacilities($organization->id, [$site->id]));
    self::assertSame([], $port->snapshots($organization->id, []));
    self::assertSame([], $port->equipmentIdsInFacilities($organization->id, []));
  }

  public function testOversizedIdentityScopesFailExplicitlyRatherThanTruncating(): void
  {
    $this->expectException(InterventionFactsScopeTooLarge::class);
    $this->port()->snapshots(self::id(1), array_map(self::id(...), range(10000, 20000)));
  }

  /**
   * Resolves the public identity bridge through its explicit main connection alias.
   *
   * @since 1.0.0
   */
  private function port(): InterventionEquipmentSnapshotPort
  {
    $port = self::getContainer()->get(InterventionEquipmentSnapshotPort::class);
    self::assertInstanceOf(InterventionEquipmentSnapshotPort::class, $port);

    return $port;
  }

  /**
   * Builds an isolated organization in the main database.
   *
   * @since 1.0.0
   */
  private function organization(int $number): OrganizationRecord
  {
    $record = new OrganizationRecord();
    $record->id = self::id($number);
    $record->name = 'Snapshot owner ' . $number;
    $record->slug = 'equipment-snapshot-owner-' . $number;
    $record->ownerUserId = $record->createdByUserId = self::id(1000 + $number);
    $record->status = 'active';
    $record->isActive = true;
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Builds a published hierarchy node owned by one organization.
   *
   * @since 1.0.0
   */
  private function facility(int $number, OrganizationRecord $organization, string $type, ?FacilityRecord $parent): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = self::id($number);
    $record->organization = $organization;
    $record->type = $type;
    $record->name = 'Facility ' . $number;
    $record->parentFacility = $parent;
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Builds an asset without synthesizing absent identity fields.
   *
   * @since 1.0.0
   */
  private function equipment(int $number, OrganizationRecord $organization, ?string $facilityId): EquipmentRecord
  {
    $record = new EquipmentRecord();
    $record->id = self::id($number);
    $record->organization = $organization;
    $record->facilityId = $facilityId;
    $record->type = 'fire_extinguisher';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Generates deterministic IDs independent from publication and shared template fixtures.
   *
   * @since 1.0.0
   */
  private static function id(int $number): string
  {
    return sprintf('ea338400-e29b-41d4-a716-%012d', $number);
  }
}
