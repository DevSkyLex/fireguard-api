<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Domain\Model\NotificationPreference;

use DateTimeImmutable;
use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NotificationPreferenceTest.
 *
 * @category Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NotificationPreference::class)]
final class NotificationPreferenceTest extends TestCase
{
  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string CATEGORY = 'organization';

  #[Test]
  public function testCreateDefaultsBothChannelsToEnabled(): void
  {
    $before = new DateTimeImmutable();

    $preference = NotificationPreference::create(
      userId: self::USER_ID,
      category: self::CATEGORY,
    );

    self::assertSame(self::USER_ID, $preference->userId());
    self::assertSame(self::CATEGORY, $preference->category());
    self::assertTrue($preference->isEmailEnabled());
    self::assertTrue($preference->isMercureEnabled());
    self::assertGreaterThanOrEqual($before, $preference->updatedAt());
  }

  #[Test]
  public function testCreateHonoursExplicitlyDisabledChannels(): void
  {
    $preference = NotificationPreference::create(
      userId: self::USER_ID,
      category: self::CATEGORY,
      emailEnabled: false,
      mercureEnabled: false,
    );

    self::assertFalse($preference->isEmailEnabled());
    self::assertFalse($preference->isMercureEnabled());
  }

  #[Test]
  public function testCreateAllowsMixedChannelState(): void
  {
    $preference = NotificationPreference::create(
      userId: self::USER_ID,
      category: self::CATEGORY,
      emailEnabled: true,
      mercureEnabled: false,
    );

    self::assertTrue($preference->isEmailEnabled());
    self::assertFalse($preference->isMercureEnabled());
  }

  #[Test]
  public function testReconstituteRoundTripsPersistedState(): void
  {
    $updatedAt = new DateTimeImmutable('2026-01-02T03:04:05+00:00');

    $preference = NotificationPreference::reconstitute(
      userId: self::USER_ID,
      category: self::CATEGORY,
      emailEnabled: false,
      mercureEnabled: true,
      updatedAt: $updatedAt,
    );

    self::assertSame(self::USER_ID, $preference->userId());
    self::assertSame(self::CATEGORY, $preference->category());
    self::assertFalse($preference->isEmailEnabled());
    self::assertTrue($preference->isMercureEnabled());
    self::assertSame($updatedAt, $preference->updatedAt());
  }
}
