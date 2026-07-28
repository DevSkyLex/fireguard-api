<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilityMapper::class)]
final class FacilityMapperTest extends TestCase
{
  #[Test]
  public function testToDomainMapsAllFields(): void
  {
    $record = new FacilityRecord();
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655441601';
    $parent = new FacilityRecord();
    $parent->id = '550e8400-e29b-41d4-a716-446655441602';
    $record->id = '550e8400-e29b-41d4-a716-446655441600';
    $record->organization = $organization;
    $record->parentFacility = $parent;
    $record->type = 'building';
    $record->name = 'Building A';
    $record->code = 'BLDG-1';
    $record->status = 'active';
    $record->address = '10 rue de la Paix';
    $record->latitude = 48.8566;
    $record->longitude = 2.3522;
    $record->metadata = ['floors' => 3];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $facility = FacilityMapper::toDomain($record);

    self::assertSame('550e8400-e29b-41d4-a716-446655441600', (string) $facility->id());
    self::assertSame('550e8400-e29b-41d4-a716-446655441601', (string) $facility->organizationId());
    self::assertSame('550e8400-e29b-41d4-a716-446655441602', (string) $facility->parentFacilityId());
    self::assertSame(FacilityType::BUILDING, $facility->type());
    self::assertSame('Building A', (string) $facility->name());
    self::assertSame('BLDG-1', $facility->code());
    self::assertSame(FacilityStatus::ACTIVE, $facility->status());
    self::assertSame('10 rue de la Paix', $facility->address());
    self::assertNotNull($facility->coordinates());
    self::assertSame(48.8566, $facility->coordinates()->latitude());
    self::assertSame(2.3522, $facility->coordinates()->longitude());
    self::assertSame(['floors' => 3], $facility->metadata());
    self::assertEquals(new DateTimeImmutable('2026-02-12T08:00:00+00:00'), $facility->createdAt());
    self::assertEquals(new DateTimeImmutable('2026-02-12T09:00:00+00:00'), $facility->updatedAt());
  }

  #[Test]
  public function testToDomainHandlesNullOptionalFields(): void
  {
    $record = new FacilityRecord();
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655441604';
    $record->id = '550e8400-e29b-41d4-a716-446655441603';
    $record->organization = $organization;
    $record->parentFacility = null;
    $record->type = 'site';
    $record->name = 'HQ';
    $record->code = null;
    $record->status = 'archived';
    $record->address = null;
    $record->metadata = [];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $facility = FacilityMapper::toDomain($record);

    self::assertNull($facility->parentFacilityId());
    self::assertNull($facility->code());
    self::assertNull($facility->address());
    self::assertNull($facility->coordinates());
    self::assertSame(FacilityStatus::ARCHIVED, $facility->status());
    self::assertEmpty($facility->metadata());
  }

  #[Test]
  public function testToDomainTreatsSingleCoordinateAsMissing(): void
  {
    $record = new FacilityRecord();
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655441704';
    $record->id = '550e8400-e29b-41d4-a716-446655441703';
    $record->organization = $organization;
    $record->type = 'site';
    $record->name = 'HQ';
    $record->status = 'active';
    $record->latitude = 48.8566;
    $record->longitude = null;
    $record->metadata = [];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $facility = FacilityMapper::toDomain($record);

    self::assertNull($facility->coordinates());
  }

  #[Test]
  public function testToRecordMapsAllFields(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441605');
    $createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');

    $facility = Facility::reconstitute(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441606'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441607'),
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 1'),
      status: FacilityStatus::ACTIVE,
      createdAt: $createdAt,
      updatedAt: $createdAt,
      parentFacilityId: $parentId,
      code: 'FLR-1',
      address: '5th Avenue',
      metadata: ['color' => 'blue'],
      coordinates: new FacilityCoordinates(48.8566, 2.3522),
    );

    $record = FacilityMapper::toRecord($facility);

    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertSame('550e8400-e29b-41d4-a716-446655441606', $record->id);
    self::assertNull($record->organization);
    self::assertNull($record->parentFacility);
    self::assertSame('floor', $record->type);
    self::assertSame('Floor 1', $record->name);
    self::assertSame('FLR-1', $record->code);
    self::assertSame('active', $record->status);
    self::assertSame('5th Avenue', $record->address);
    self::assertSame(48.8566, $record->latitude);
    self::assertSame(2.3522, $record->longitude);
    self::assertSame(['color' => 'blue'], $record->metadata);
    self::assertEquals($createdAt, $record->createdAt);
    self::assertEquals($createdAt, $record->updatedAt);
  }

  #[Test]
  public function testToRecordHandlesNullOptionalFields(): void
  {
    $now = new DateTimeImmutable('2026-02-12T08:00:00+00:00');

    $facility = Facility::reconstitute(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441608'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441609'),
      type: FacilityType::AREA,
      name: new FacilityName('Area Z'),
      status: FacilityStatus::ARCHIVED,
      createdAt: $now,
      updatedAt: $now,
    );

    $record = FacilityMapper::toRecord($facility);

    self::assertNull($record->parentFacility);
    self::assertNull($record->code);
    self::assertNull($record->address);
    self::assertNull($record->latitude);
    self::assertNull($record->longitude);
    self::assertSame('archived', $record->status);
    self::assertSame([], $record->metadata);
  }

  #[Test]
  public function testToDomainAndToRecordRoundTrip(): void
  {
    $record = new FacilityRecord();
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655441611';
    $record->id = '550e8400-e29b-41d4-a716-446655441610';
    $record->organization = $organization;
    $record->parentFacility = null;
    $record->type = 'zone';
    $record->name = 'Zone Alpha';
    $record->code = 'ZONE-A';
    $record->status = 'active';
    $record->address = '42 Maple Street';
    $record->latitude = 43.2965;
    $record->longitude = 5.3698;
    $record->metadata = ['priority' => 1];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $facility = FacilityMapper::toDomain($record);
    $roundTripped = FacilityMapper::toRecord($facility);

    self::assertSame($record->id, $roundTripped->id);
    self::assertNull($roundTripped->organization);
    self::assertNull($roundTripped->parentFacility);
    self::assertSame($record->type, $roundTripped->type);
    self::assertSame($record->name, $roundTripped->name);
    self::assertSame($record->code, $roundTripped->code);
    self::assertSame($record->status, $roundTripped->status);
    self::assertSame($record->address, $roundTripped->address);
    self::assertSame($record->latitude, $roundTripped->latitude);
    self::assertSame($record->longitude, $roundTripped->longitude);
    self::assertSame($record->metadata, $roundTripped->metadata);
    self::assertEquals($record->createdAt, $roundTripped->createdAt);
    self::assertEquals($record->updatedAt, $roundTripped->updatedAt);
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutAnOrganization(): void
  {
    $record = new FacilityRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655441620';
    $record->organization = null;
    $record->type = 'building';
    $record->name = 'Orphan';
    $record->status = 'active';
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Facility record must reference an organization.');

    FacilityMapper::toDomain($record);
  }
}
