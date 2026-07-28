<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, TagId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\TagMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\TagRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TagMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TagMapper::class)]
final class TagMapperTest extends TestCase
{
  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655494001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655494002';

  #[Test]
  public function testToDomainMapsTheRecordColumns(): void
  {
    $tag = TagMapper::toDomain($this->record());

    self::assertSame(self::TAG_ID, (string) $tag->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $tag->organizationId());
    self::assertSame('Étage 2', $tag->name());
    self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $tag->createdAt());
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Tag record must reference an organization.');

    TagMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordMapsTheAggregateWithoutTheOrganizationAssociation(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-03T09:30:00+00:00');

    $tag = Tag::reconstitute(
      id: TagId::fromString(self::TAG_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Critique',
      createdAt: $createdAt,
    );

    $record = TagMapper::toRecord($tag);

    self::assertSame(self::TAG_ID, $record->id);
    self::assertSame('Critique', $record->name);
    self::assertEquals($createdAt, $record->createdAt);
    self::assertNull($record->organization);
  }

  #[Test]
  public function testARoundTripPreservesTheTagIdentity(): void
  {
    $record = TagMapper::toRecord(TagMapper::toDomain($this->record()));
    $record->organization = $this->organization();

    $roundTripped = TagMapper::toDomain($record);

    self::assertSame(self::TAG_ID, (string) $roundTripped->id());
    self::assertSame('Étage 2', $roundTripped->name());
  }

  private function organization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    return $organization;
  }

  private function record(): TagRecord
  {
    $record = new TagRecord();
    $record->id = self::TAG_ID;
    $record->organization = $this->organization();
    $record->name = 'Étage 2';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }
}
