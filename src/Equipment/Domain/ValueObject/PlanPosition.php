<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function is_float;
use function is_int;
use function is_string;

/**
 * ValueObject PlanPosition.
 *
 * Pins one piece of equipment at a single point over a floor plan attachment.
 * Coordinates are normalized image-space [0, 1] fractions of the plan's
 * width/height, not pixels, mirroring
 * `Facility\Domain\ValueObject\PlanGeometry` — but Equipment-owned: this
 * module never imports Facility's Domain, so the shape is duplicated rather
 * than shared.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanPosition
{
  // #region Properties
  private string $attachmentId;

  private float $x;

  private float $y;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanPosition class.
   *
   * @since 1.0.0
   *
   * @param string $attachmentId the floor plan attachment identifier the position is bound to
   * @param float $x the normalized x coordinate, in [0, 1]
   * @param float $y the normalized y coordinate, in [0, 1]
   */
  public function __construct(string $attachmentId, float $x, float $y)
  {
    // Validates the UUID shape without keeping the Uuid instance around —
    // this value object exposes a plain string, matching every other id
    // carried by a JSONB-serialized shape in this module.
    new Uuid($attachmentId);
    $this->attachmentId = $attachmentId;
    $this->x = self::assertNormalized($x, 'x');
    $this->y = self::assertNormalized($y, 'y');
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * Reconstitutes a plan position from its persisted (or wire) shape:
   * `{"attachmentId": "<uuid>", "x": float, "y": float}`.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array{attachmentId?: mixed, x?: mixed, y?: mixed} $data the shape to reconstitute from
   *
   * @return self the reconstituted plan position
   */
  public static function fromArray(array $data): self
  {
    $attachmentId = $data['attachmentId'] ?? null;
    $x = $data['x'] ?? null;
    $y = $data['y'] ?? null;

    if (!is_string($attachmentId)) {
      throw InvalidValueException::because('Plan position "attachmentId" must be a string.');
    }

    if (!is_int($x) && !is_float($x)) {
      throw InvalidValueException::because('Plan position "x" must be numeric.');
    }

    if (!is_int($y) && !is_float($y)) {
      throw InvalidValueException::because('Plan position "y" must be numeric.');
    }

    return new self(
      attachmentId: $attachmentId,
      x: (float) $x,
      y: (float) $y,
    );
  }

  /**
   * Method attachmentId.
   *
   * @since 1.0.0
   */
  public function attachmentId(): string
  {
    return $this->attachmentId;
  }

  /**
   * Method x.
   *
   * @since 1.0.0
   */
  public function x(): float
  {
    return $this->x;
  }

  /**
   * Method y.
   *
   * @since 1.0.0
   */
  public function y(): float
  {
    return $this->y;
  }

  /**
   * Method toArray.
   *
   * Serializes to the persisted/wire shape:
   * `{"attachmentId": "<uuid>", "x": float, "y": float}`.
   *
   * @since 1.0.0
   *
   * @return array{attachmentId: string, x: float, y: float} the serialized shape
   */
  public function toArray(): array
  {
    return [
      'attachmentId' => $this->attachmentId,
      'x' => $this->x,
      'y' => $this->y,
    ];
  }

  /**
   * Method equals.
   *
   * @since 1.0.0
   *
   * @param self $other the plan position to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->toArray() === $other->toArray();
  }

  /**
   * Method assertNormalized.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param float $value the coordinate to validate
   * @param string $axis the axis name, for the error message
   *
   * @return float the validated coordinate
   */
  private static function assertNormalized(float $value, string $axis): float
  {
    if ($value < 0.0 || $value > 1.0) {
      throw InvalidValueException::because('Plan position "' . $axis . '" must be normalized between 0 and 1.');
    }

    return $value;
  }
  // #endregion
}
