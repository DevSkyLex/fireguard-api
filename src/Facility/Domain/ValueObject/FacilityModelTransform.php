<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityModelException;

use function is_array;
use function is_finite;
use function is_float;
use function is_int;

/**
 * ValueObject FacilityModelTransform.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelTransform
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param float $scale the scale
   * @param float $rotationDegrees the rotation degrees
   * @param float $x the x
   * @param float $y the y
   * @param float $z the z
   *
   * @return void no return value
   */
  public function __construct(
    public float $scale = 1.0,
    public float $rotationDegrees = 0.0,
    public float $x = 0.0,
    public float $y = 0.0,
    public float $z = 0.0,
  ) {
    foreach ([$scale, $rotationDegrees, $x, $y, $z] as $number) {
      if (!is_finite($number)) {
        throw FacilityModelException::invalid('Model transformations must be finite.');
      }
    }
    if ($scale <= 0.0) {
      throw FacilityModelException::invalid('Model scale must be positive.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * Validates the complete numeric transformation in the common metric coordinate system.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $value
   *
   * @return self the operation result
   */
  public static function fromArray(array $value): self
  {
    $translation = $value['translation'] ?? null;
    if (!is_array($translation)) {
      throw FacilityModelException::invalid('A model translation is required.');
    }

    return new self(
      self::number($value['scale'] ?? null),
      self::number($value['rotationDegrees'] ?? null),
      self::number($translation['x'] ?? null),
      self::number($translation['y'] ?? null),
      self::number($translation['z'] ?? null),
    );
  }

  /**
   * Method toArray.
   *
   * Returns the scalar representation used by persistence and transport contracts.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array{scale: float, rotationDegrees: float, translation: array{x: float, y: float, z: float}}
   */
  public function toArray(): array
  {
    return ['scale' => $this->scale, 'rotationDegrees' => $this->rotationDegrees,
      'translation' => ['x' => $this->x, 'y' => $this->y, 'z' => $this->z]];
  }

  /**
   * Method number.
   *
   * Reads one strictly numeric transform component.
   *
   * @access private
   * @since 1.0.0
   *
   * @param mixed $number the number
   *
   * @return float the operation result
   */
  private static function number(mixed $number): float
  {
    if (!is_int($number) && !is_float($number)) {
      throw FacilityModelException::invalid('A complete numeric model transformation is required.');
    }

    return (float) $number;
  }
  // #endregion
}
