<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(Facility::class)]
final class FacilityTest extends TestCase
{
  private FacilityId $id;

  private FacilityOrganizationId $organizationId;

  protected function setUp(): void
  {
    $this->id = new FacilityId('550e8400-e29b-41d4-a716-446655441500');
    $this->organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441501');
  }

  #[Test]
  public function testCreateSetsStatusToActive(): void
  {
    $facility = Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    self::assertSame(FacilityStatus::ACTIVE, $facility->status());
    self::assertTrue($facility->status()->isActive());
  }

  #[Test]
  public function testCreateSetsAllFields(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441502');

    $facility = Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building A'),
      parentFacilityId: $parentId,
      code: '  BLDG-1  ',
      address: '  10 rue de la Paix  ',
      metadata: ['floor_count' => 5, '' => 'ignored'],
    );

    self::assertSame('550e8400-e29b-41d4-a716-446655441500', (string) $facility->id());
    self::assertSame('550e8400-e29b-41d4-a716-446655441501', (string) $facility->organizationId());
    self::assertSame('550e8400-e29b-41d4-a716-446655441502', (string) $facility->parentFacilityId());
    self::assertSame(FacilityType::BUILDING, $facility->type());
    self::assertSame('Building A', (string) $facility->name());
    self::assertSame('BLDG-1', $facility->code());
    self::assertSame('10 rue de la Paix', $facility->address());
    self::assertSame(['floor_count' => 5], $facility->metadata());
  }

  #[Test]
  public function testReconstituteRestoresArchivedStatus(): void
  {
    $now = new DateTimeImmutable('2026-02-12T10:00:00+00:00');

    $facility = Facility::reconstitute(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::ZONE,
      name: new FacilityName('Zone B'),
      status: FacilityStatus::ARCHIVED,
      createdAt: $now,
      updatedAt: $now,
    );

    self::assertSame(FacilityStatus::ARCHIVED, $facility->status());
    self::assertFalse($facility->status()->isActive());
    self::assertEquals($now, $facility->createdAt());
    self::assertEquals($now, $facility->updatedAt());
  }

  #[Test]
  public function testRenameUpdatesName(): void
  {
    $facility = $this->makeActiveFacility();
    $facility->rename(new FacilityName('New Name'));

    self::assertSame('New Name', (string) $facility->name());
  }

  #[Test]
  public function testChangeTypeUpdatesType(): void
  {
    $facility = $this->makeActiveFacility();
    $facility->changeType(FacilityType::FLOOR);

    self::assertSame(FacilityType::FLOOR, $facility->type());
  }

  #[Test]
  public function testMoveToUpdatesParentFacilityId(): void
  {
    $parent = new FacilityId('550e8400-e29b-41d4-a716-446655441503');
    $facility = $this->makeActiveFacility();

    $facility->moveTo($parent);
    self::assertSame('550e8400-e29b-41d4-a716-446655441503', (string) $facility->parentFacilityId());

    $facility->moveTo(null);
    self::assertNull($facility->parentFacilityId());
  }

  #[Test]
  public function testChangeCodeNormalizesAndUpdates(): void
  {
    $facility = $this->makeActiveFacility();

    $facility->changeCode('  SITE-01  ');
    self::assertSame('SITE-01', $facility->code());

    $facility->changeCode('');
    self::assertNull($facility->code());

    $facility->changeCode(null);
    self::assertNull($facility->code());
  }

  #[Test]
  public function testChangeCodeThrowsWhenExceedsMaxLength(): void
  {
    $facility = $this->makeActiveFacility();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Facility code must be at most 80 characters.');

    $facility->changeCode(str_repeat('X', 81));
  }

  #[Test]
  public function testChangeAddressNormalizesAndUpdates(): void
  {
    $facility = $this->makeActiveFacility();

    $facility->changeAddress('  10 rue  ');
    self::assertSame('10 rue', $facility->address());

    $facility->changeAddress('   ');
    self::assertNull($facility->address());
  }

  #[Test]
  public function testChangeAddressThrowsWhenExceedsMaxLength(): void
  {
    $facility = $this->makeActiveFacility();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Facility address must be at most 255 characters.');

    $facility->changeAddress(str_repeat('A', 256));
  }

  #[Test]
  public function testChangeMetadataFiltersNonStringKeys(): void
  {
    $facility = $this->makeActiveFacility();

    $facility->changeMetadata(['valid' => 'yes', '' => 'no', 'other' => 42]);
    self::assertSame(['valid' => 'yes', 'other' => 42], $facility->metadata());
  }

  #[Test]
  public function testArchiveSetsStatusToArchived(): void
  {
    $facility = $this->makeActiveFacility();
    $facility->archive();

    self::assertSame(FacilityStatus::ARCHIVED, $facility->status());
    self::assertFalse($facility->status()->isActive());
  }

  #[Test]
  public function testArchiveIsIdempotent(): void
  {
    $facility = $this->makeActiveFacility();

    $facility->archive();
    $updatedAtAfterFirst = $facility->updatedAt();

    $facility->archive();

    self::assertSame(FacilityStatus::ARCHIVED, $facility->status());
    self::assertSame($updatedAtAfterFirst, $facility->updatedAt());
  }

  #[Test]
  public function testCreateWithNullCodeAndAddressSetsNullValues(): void
  {
    $facility = Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::AREA,
      name: new FacilityName('Area One'),
      code: null,
      address: null,
    );

    self::assertNull($facility->code());
    self::assertNull($facility->address());
    self::assertNull($facility->parentFacilityId());
    self::assertEmpty($facility->metadata());
  }

  #[Test]
  public function testCreateCodeNullOnBlankString(): void
  {
    $facility = Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('HQ'),
      code: '   ',
    );

    self::assertNull($facility->code());
  }

  #[Test]
  public function testCreateAddressNullOnBlankString(): void
  {
    $facility = Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('HQ'),
      address: '   ',
    );

    self::assertNull($facility->address());
  }

  private function makeActiveFacility(): Facility
  {
    return Facility::create(
      id: $this->id,
      organizationId: $this->organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Main Facility'),
    );
  }
}
