<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function is_finite;
use function is_float;
use function is_int;

/**
 * ValueObject PlanCalibration.
 *
 * Uniform metric scale and placement of an image plan in its building.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanCalibration
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    public float $widthMeters,
    public float $rotationDegrees,
    public float $offsetXMeters,
    public float $offsetZMeters,
  ) {
    if (!is_finite($widthMeters) || $widthMeters <= 0.0 || $widthMeters > 100000.0) {
      throw InvalidValueException::because('Plan width must be finite, positive and at most 100000 metres.');
    }
    if (!is_finite($rotationDegrees) || $rotationDegrees < -360.0 || $rotationDegrees > 360.0) {
      throw InvalidValueException::because('Plan rotation must be between -360 and 360 degrees.');
    }
    foreach ([$offsetXMeters, $offsetZMeters] as $offset) {
      if (!is_finite($offset) || $offset < -100000.0 || $offset > 100000.0) {
        throw InvalidValueException::because('Plan offsets must be finite and between -100000 and 100000 metres.');
      }
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the stored or requested calibration
   */
  public static function fromArray(array $data): self
  {
    return new self(self::number($data, 'widthMeters'), self::number($data, 'rotationDegrees'), self::number($data, 'offsetXMeters'), self::number($data, 'offsetZMeters'));
  }

  /**
   * Method toArray.
   *
   * @since 1.0.0
   *
   * @return array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}
   */
  public function toArray(): array
  {
    return ['widthMeters' => $this->widthMeters, 'rotationDegrees' => $this->rotationDegrees, 'offsetXMeters' => $this->offsetXMeters, 'offsetZMeters' => $this->offsetZMeters];
  }

  /**
   * Method number.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the requested calibration
   */
  private static function number(array $data, string $field): float
  {
    $value = $data[$field] ?? null;
    if (!is_int($value) && !is_float($value)) {
      throw InvalidValueException::because('Plan calibration ' . $field . ' must be numeric.');
    }

    return (float) $value;
  }
  // #endregion
}
