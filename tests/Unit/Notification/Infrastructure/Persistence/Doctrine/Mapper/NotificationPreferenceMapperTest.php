<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationPreferenceMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationPreferenceRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NotificationPreferenceMapperTest.
 *
 * The mapper is the only translation between the opt-out a user saved and
 * the row the dispatcher later reads. A channel flag dropped or swapped in
 * either direction means notifications keep arriving after someone turned
 * them off, so the round trip is asserted in both directions.
 *
 * @category Mapper Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NotificationPreferenceMapper::class)]
final class NotificationPreferenceMapperTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655474001';
  // #endregion

  // #region Methods
  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $preference = NotificationPreference::reconstitute(
      userId: self::USER_ID,
      category: 'organization',
      emailEnabled: false,
      mercureEnabled: true,
      updatedAt: new DateTimeImmutable('2026-01-05T12:00:00+00:00'),
    );

    $record = NotificationPreferenceMapper::toRecord($preference);

    self::assertSame(self::USER_ID, $record->userId);
    self::assertSame('organization', $record->category);
    self::assertFalse($record->emailEnabled);
    self::assertTrue($record->mercureEnabled);
    self::assertEquals(new DateTimeImmutable('2026-01-05T12:00:00+00:00'), $record->updatedAt);
  }

  #[Test]
  public function testToDomainRebuildsThePreference(): void
  {
    $record = new NotificationPreferenceRecord();
    $record->userId = self::USER_ID;
    $record->category = 'equipment';
    $record->emailEnabled = true;
    $record->mercureEnabled = false;
    $record->updatedAt = new DateTimeImmutable('2026-02-06T08:15:00+00:00');

    $preference = NotificationPreferenceMapper::toDomain($record);

    self::assertSame(self::USER_ID, $preference->userId());
    self::assertSame('equipment', $preference->category());
    self::assertTrue($preference->isEmailEnabled());
    self::assertFalse($preference->isMercureEnabled());
    self::assertEquals(new DateTimeImmutable('2026-02-06T08:15:00+00:00'), $preference->updatedAt());
  }

  #[Test]
  public function testRoundTripPreservesBothChannelFlags(): void
  {
    $original = NotificationPreference::reconstitute(
      userId: self::USER_ID,
      category: 'system',
      emailEnabled: false,
      mercureEnabled: false,
      updatedAt: new DateTimeImmutable('2026-03-07T18:45:00+00:00'),
    );

    $restored = NotificationPreferenceMapper::toDomain(
      NotificationPreferenceMapper::toRecord($original),
    );

    self::assertSame($original->userId(), $restored->userId());
    self::assertSame($original->category(), $restored->category());
    self::assertSame($original->isEmailEnabled(), $restored->isEmailEnabled());
    self::assertSame($original->isMercureEnabled(), $restored->isMercureEnabled());
    self::assertEquals($original->updatedAt(), $restored->updatedAt());
  }
  // #endregion
}
