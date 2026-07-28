<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Infrastructure\Persistence\Doctrine\Mapper;

use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use Calendar\Infrastructure\Persistence\Doctrine\Mapper\CalendarEventMapper;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarEventRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarEventMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventMapper::class)]
final class CalendarEventMapperTest extends TestCase
{
  // #region Constants
  private const string EVENT_ID = '0199a7c1-0000-7000-8000-0000000000e1';

  private const string ORGANIZATION_ID = '0199a7c1-0000-7000-8000-0000000000e2';

  private const string MEMBER_ID = '0199a7c1-0000-7000-8000-0000000000e3';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $startsAt = new DateTimeImmutable('2026-04-01T09:00:00+00:00');
    $endsAt = new DateTimeImmutable('2026-04-01T11:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-02T09:00:00+00:00');

    $record = new CalendarEventRecord();
    $record->id = self::EVENT_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->title = 'Annual fire drill';
    $record->description = 'Full building evacuation.';
    $record->startsAt = $startsAt;
    $record->endsAt = $endsAt;
    $record->allDay = false;
    $record->facilityId = 'facility-1';
    $record->createdByMemberId = self::MEMBER_ID;
    $record->createdAt = $createdAt;
    $record->updatedAt = $updatedAt;

    $event = CalendarEventMapper::toDomain($record);

    self::assertSame(self::EVENT_ID, (string) $event->id());
    self::assertSame(self::ORGANIZATION_ID, $event->organizationId());
    self::assertSame('Annual fire drill', $event->title());
    self::assertSame('Full building evacuation.', $event->description());
    self::assertSame($startsAt, $event->startsAt());
    self::assertSame($endsAt, $event->endsAt());
    self::assertFalse($event->allDay());
    self::assertSame('facility-1', $event->facilityId());
    self::assertSame(self::MEMBER_ID, $event->createdByMemberId());
    self::assertSame($createdAt, $event->createdAt());
    self::assertSame($updatedAt, $event->updatedAt());
  }

  #[Test]
  public function testToDomainKeepsNullableFieldsNull(): void
  {
    $timestamp = new DateTimeImmutable('2026-04-02T00:00:00+00:00');

    $record = new CalendarEventRecord();
    $record->id = self::EVENT_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->title = 'All-day inspection';
    $record->startsAt = $timestamp;
    $record->allDay = true;
    $record->createdByMemberId = self::MEMBER_ID;
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;

    $event = CalendarEventMapper::toDomain($record);

    self::assertNull($event->description());
    self::assertNull($event->endsAt());
    self::assertNull($event->facilityId());
    self::assertTrue($event->allDay());
  }

  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $startsAt = new DateTimeImmutable('2026-05-01T09:00:00+00:00');
    $endsAt = new DateTimeImmutable('2026-05-01T10:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-04-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-04-05T09:00:00+00:00');

    $event = CalendarEvent::reconstitute(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Extinguisher servicing',
      description: 'Third-party contractor visit.',
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: false,
      facilityId: 'facility-2',
      createdByMemberId: self::MEMBER_ID,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    $record = new CalendarEventRecord();

    CalendarEventMapper::toRecord($event, $record);

    self::assertSame(self::EVENT_ID, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('Extinguisher servicing', $record->title);
    self::assertSame('Third-party contractor visit.', $record->description);
    self::assertSame($startsAt, $record->startsAt);
    self::assertSame($endsAt, $record->endsAt);
    self::assertFalse($record->allDay);
    self::assertSame('facility-2', $record->facilityId);
    self::assertSame(self::MEMBER_ID, $record->createdByMemberId);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);
  }

  #[Test]
  public function testRoundTripPreservesState(): void
  {
    $timestamp = new DateTimeImmutable('2026-06-01T08:00:00+00:00');

    $original = new CalendarEventRecord();
    $original->id = self::EVENT_ID;
    $original->organizationId = self::ORGANIZATION_ID;
    $original->title = 'Round trip';
    $original->description = null;
    $original->startsAt = $timestamp;
    $original->endsAt = null;
    $original->allDay = true;
    $original->facilityId = null;
    $original->createdByMemberId = self::MEMBER_ID;
    $original->createdAt = $timestamp;
    $original->updatedAt = $timestamp;

    $roundTripped = new CalendarEventRecord();
    CalendarEventMapper::toRecord(CalendarEventMapper::toDomain($original), $roundTripped);

    self::assertSame($original->id, $roundTripped->id);
    self::assertSame('Round trip', $roundTripped->title);
    self::assertNull($roundTripped->description);
    self::assertNull($roundTripped->endsAt);
    self::assertNull($roundTripped->facilityId);
    self::assertTrue($roundTripped->allDay);
  }
  // #endregion
}
