<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use DateTimeImmutable;
use DateTimeZone;

use function sprintf;

/**
 * DataFixtures SeedTimeline.
 *
 * Keeps the demo dataset ALIVE over time. Seed fixtures are authored with
 * readable absolute date literals anchored on {@see self::ANCHOR}; this
 * helper shifts every such literal by a whole number of days so the most
 * recent seeded event always lands {@see self::LATEST_OFFSET_DAYS} days
 * before "now", while preserving the exact relative spacing (and therefore
 * the narrative ordering) the fixtures were written with.
 *
 * Without it, dashboards that window on "the last N days" — inspection
 * trends, calendar feeds, due-status rollups — silently render empty as soon
 * as the hardcoded month falls out of range.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SeedTimeline
{
  // #region Constants
  /**
   * Constant ANCHOR.
   *
   * The instant every absolute literal in the seed fixtures is authored
   * relative to: the most recent seeded event. Bump it only when the
   * fixtures gain a literal beyond it.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ANCHOR = '2026-04-19T10:00:00+00:00';

  /**
   * Constant LATEST_OFFSET_DAYS.
   *
   * How far in the past the anchor lands after shifting, so the freshest
   * seeded record reads as "a couple of days ago" rather than "in the
   * future".
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int LATEST_OFFSET_DAYS = 2;
  // #endregion

  // #region Properties
  /**
   * Property shiftDays.
   *
   * Memoized so every fixture in one load run shares a single shift, even if
   * the run straddles midnight.
   *
   * @since 1.0.0
   */
  private static ?int $shiftDays = null;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this helper only exposes statics.
   *
   * @since 1.0.0
   */
  private function __construct()
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method now.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the current instant, in UTC
   */
  public static function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
  }

  /**
   * Method at.
   *
   * Shifts an anchored literal onto the live timeline.
   *
   * @since 1.0.0
   *
   * @param string $literal an absolute date literal anchored on {@see self::ANCHOR}
   *
   * @return DateTimeImmutable the shifted instant
   */
  public static function at(string $literal): DateTimeImmutable
  {
    return new DateTimeImmutable($literal)->modify(sprintf('%+d days', self::shiftDays()));
  }

  /**
   * Method fromNow.
   *
   * Builds an instant directly from an offset relative to "now", for data
   * that has no anchored literal to shift (calendar events, maintenance due
   * dates).
   *
   * @since 1.0.0
   *
   * @param int $days the day offset (negative for the past)
   * @param int $hour the hour of day, in UTC
   * @param int $minute the minute of hour
   *
   * @return DateTimeImmutable the resulting instant
   */
  public static function fromNow(int $days, int $hour = 9, int $minute = 0): DateTimeImmutable
  {
    return self::now()
      ->modify(sprintf('%+d days', $days))
      ->setTime($hour, $minute);
  }

  /**
   * Method shiftDays.
   *
   * @since 1.0.0
   *
   * @return int the whole-day shift applied to every anchored literal
   */
  private static function shiftDays(): int
  {
    if (null === self::$shiftDays) {
      $anchor = new DateTimeImmutable(self::ANCHOR);
      $target = self::now()->modify(sprintf('-%d days', self::LATEST_OFFSET_DAYS));
      self::$shiftDays = (int) $anchor->diff($target)->format('%r%a');
    }

    return self::$shiftDays;
  }
  // #endregion
}
