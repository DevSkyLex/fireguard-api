<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\{RecurrenceFrequency, RecurrenceRule};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RecurrenceRuleTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecurrenceRule::class)]
final class RecurrenceRuleTest extends TestCase
{
  #[Test]
  public function testConstructorRejectsAnIntervalBelowTheMinimum(): void
  {
    $this->expectException(InterventionValidationException::class);

    new RecurrenceRule(RecurrenceFrequency::MONTHLY, 0, new DateTimeImmutable('2026-01-01'));
  }

  #[Test]
  public function testConstructorRejectsAnIntervalAboveTheMaximum(): void
  {
    $this->expectException(InterventionValidationException::class);

    new RecurrenceRule(RecurrenceFrequency::MONTHLY, 13, new DateTimeImmutable('2026-01-01'));
  }

  #[Test]
  public function testConstructorAcceptsTheIntervalBounds(): void
  {
    $min = new RecurrenceRule(RecurrenceFrequency::MONTHLY, 1, new DateTimeImmutable('2026-01-01'));
    $max = new RecurrenceRule(RecurrenceFrequency::MONTHLY, 12, new DateTimeImmutable('2026-01-01'));

    self::assertSame(1, $min->interval);
    self::assertSame(12, $max->interval);
  }

  #[Test]
  public function testFromValuesRejectsAnUnknownFrequency(): void
  {
    $this->expectException(InterventionValidationException::class);

    RecurrenceRule::fromValues('fortnightly', 1, new DateTimeImmutable('2026-01-01'));
  }

  #[Test]
  public function testAssertTimezoneRejectsAnInvalidIdentifier(): void
  {
    $this->expectException(InterventionValidationException::class);

    RecurrenceRule::assertTimezone('Not/AZone');
  }

  #[Test]
  public function testWeeklyAdvancesByTheIntervalInWeeks(): void
  {
    $rule = RecurrenceRule::fromValues('weekly', 2, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2026-01-15T00:00:00+00:00', $next->format('c'));
  }

  #[Test]
  public function testMonthlyAdvancesByTheIntervalInMonths(): void
  {
    $rule = RecurrenceRule::fromValues('monthly', 2, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2026-03-01T00:00:00+00:00', $next->format('c'));
  }

  #[Test]
  public function testQuarterlyAdvancesByThreeMonthsPerInterval(): void
  {
    $rule = RecurrenceRule::fromValues('quarterly', 1, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2026-04-01T00:00:00+00:00', $next->format('c'));
  }

  #[Test]
  public function testSemiannualAdvancesBySixMonthsPerInterval(): void
  {
    $rule = RecurrenceRule::fromValues('semiannual', 1, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2026-07-01T00:00:00+00:00', $next->format('c'));
  }

  #[Test]
  public function testAnnualAdvancesByTwelveMonthsPerInterval(): void
  {
    $rule = RecurrenceRule::fromValues('annual', 1, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2027-01-01T00:00:00+00:00', $next->format('c'));
  }

  #[Test]
  public function testNextAfterReturnsTheAnchorItselfWhenFromIsBeforeIt(): void
  {
    // The anchor already qualifies as "strictly after `$from`": the walk
    // must not advance past it needlessly.
    $rule = RecurrenceRule::fromValues('monthly', 1, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2025-06-01T00:00:00+00:00'), 'UTC');

    self::assertSame('2026-01-01T00:00:00+00:00', $next->format('c'));
    self::assertTrue($next > new DateTimeImmutable('2025-06-01T00:00:00+00:00'));
  }

  #[Test]
  public function testEndOfMonthOverflowDriftsTheOccurrenceDayPermanently(): void
  {
    // Documented PHP DateInterval quirk: adding one calendar month to
    // January 31st, 2026 (a non-leap year) overflows past February's 28
    // days into March 3rd, rather than clamping to February 28th.
    $rule = RecurrenceRule::fromValues('monthly', 1, new DateTimeImmutable('2026-01-31T00:00:00+00:00'));

    $firstNext = $rule->nextAfter(new DateTimeImmutable('2026-01-31T00:00:00+00:00'), 'UTC');
    self::assertSame('2026-03-03T00:00:00+00:00', $firstNext->format('c'));

    // The next step is added from the drifted cursor (March 3rd), not
    // re-derived from the original anchor day (the 31st): the following
    // occurrence is April 3rd, not April 30th/May 1st.
    $secondNext = $rule->nextAfter($firstNext, 'UTC');
    self::assertSame('2026-04-03T00:00:00+00:00', $secondNext->format('c'));
  }

  #[Test]
  public function testTimezoneEdgeCaseWalksTheRuleInLocalWallClockTime(): void
  {
    // Anchored at local midnight in Europe/Paris (winter, UTC+1). Two months
    // later (April 15th) falls after the Northern-hemisphere spring-forward
    // (the last Sunday of March, the 29th in 2026): the computed occurrence
    // must still be local midnight, now at the CEST (+02:00) offset.
    $rule = RecurrenceRule::fromValues('monthly', 2, new DateTimeImmutable('2026-02-15T00:00:00+00:00'));

    $next = $rule->nextAfter(new DateTimeImmutable('2026-02-15T00:00:00+00:00'), 'Europe/Paris');

    $localNext = $next->setTimezone(new DateTimeZone('Europe/Paris'));
    self::assertSame('2026-04-15T00:00:00+02:00', $localNext->format('c'));
  }
}
