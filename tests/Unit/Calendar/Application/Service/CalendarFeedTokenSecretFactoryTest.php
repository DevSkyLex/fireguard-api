<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\Service;

use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function hash;
use function strlen;

/**
 * Test CalendarFeedTokenSecretFactoryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarFeedTokenSecretFactory::class)]
final class CalendarFeedTokenSecretFactoryTest extends TestCase
{
  #[Test]
  public function itGeneratesAUrlSafeSecretOfAtLeast32BytesOfEntropy(): void
  {
    $factory = new CalendarFeedTokenSecretFactory();

    $secret = $factory->generate();

    // 32 random bytes base64url-encode to exactly 43 characters (no padding).
    self::assertSame(43, strlen($secret));
    self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $secret);
  }

  #[Test]
  public function itNeverGeneratesTheSameSecretTwice(): void
  {
    $factory = new CalendarFeedTokenSecretFactory();

    self::assertNotSame($factory->generate(), $factory->generate());
  }

  #[Test]
  public function itHashesWithSha256(): void
  {
    $factory = new CalendarFeedTokenSecretFactory();

    self::assertSame(hash('sha256', 'secret-value'), $factory->hash('secret-value'));
    self::assertSame(64, strlen($factory->hash($factory->generate())));
  }
}
