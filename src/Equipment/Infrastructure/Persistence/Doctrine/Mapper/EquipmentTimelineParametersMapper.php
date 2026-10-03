<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Equipment\Infrastructure\Exception\InvalidStorageTimeZoneException;
use Exception;

/**
 * Class EquipmentTimelineParametersMapper.
 *
 * Converts timeline bounds into the database storage timezone and bucket timezone.
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentTimelineParametersMapper
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $storageTimeZone the configured PostgreSQL timestamp storage timezone
   *
   * @return void no return value
   */
  public function __construct(private string $storageTimeZone)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method forRange.
   *
   * Preserves timestamp microseconds and the lower bound's implicit bucket timezone.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $from the inclusive lower timestamp bound
   * @param string $to the inclusive upper timestamp bound
   * @param ?string $timeZone the optional explicit calendar-day timezone
   *
   * @return array{storageTimeZone: string, bucketTimeZone: string, createdAtFrom: string, createdAtTo: string} the normalized SQL parameters
   */
  public function forRange(string $from, string $to, ?string $timeZone): array
  {
    $bucketTimeZone = null !== $timeZone && '' !== $timeZone
      ? new DateTimeZone($timeZone)
      : new DateTimeImmutable($from)->getTimezone();
    $storageTimeZone = $this->storageTimeZone();

    return [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'createdAtFrom' => $this->timestamp($from, $storageTimeZone),
      'createdAtTo' => $this->timestamp($to, $storageTimeZone),
    ];
  }

  /**
   * Method storageTimeZone.
   *
   * Reports invalid database timezone configuration explicitly.
   *
   * @access private
   * @since 1.0.0
   *
   * @return DateTimeZone the configured timezone
   */
  private function storageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new InvalidStorageTimeZoneException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  /**
   * Method timestamp.
   *
   * Converts one bound without losing microsecond precision.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $value the timestamp bound
   * @param DateTimeZone $storageTimeZone the database timezone
   *
   * @return string the database timestamp parameter
   */
  private function timestamp(string $value, DateTimeZone $storageTimeZone): string
  {
    return new DateTimeImmutable($value)->setTimezone($storageTimeZone)->format('Y-m-d H:i:s.u');
  }
  // #endregion
}
