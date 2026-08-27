<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * ValueObject PlanGeometry.
 *
 * Binds a facility (a spatial zone) to a polygon drawn over an ancestor's
 * floor plan attachment. Coordinates are normalized image-space [0, 1]
 * fractions of the plan's width/height, not pixels, so the shape survives
 * the plan being re-rendered at a different resolution.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanGeometry
{
  // #region Constants
  /**
   * Constant MIN_POINTS.
   *
   * A polygon needs at least three points to enclose an area.
   *
   * @since 1.0.0
   */
  private const int MIN_POINTS = 3;
  // #endregion

  // #region Properties
  private string $attachmentId;

  /**
   * @var list<array{0: float, 1: float}>
   */
  private array $points;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanGeometry class.
   *
   * @since 1.0.0
   *
   * @param string $attachmentId the floor plan attachment identifier the geometry is bound to
   * @param list<array{0: float, 1: float}> $points the polygon points, at least 3, each coordinate in [0, 1]
   */
  public function __construct(string $attachmentId, array $points)
  {
    // Validates the UUID shape without keeping the Uuid instance around —
    // this value object exposes a plain string, matching every other id
    // carried by a JSONB-serialized shape in this module.
    new Uuid($attachmentId);
    $this->attachmentId = $attachmentId;

    if (count($points) < self::MIN_POINTS) {
      throw InvalidValueException::because('Plan geometry must have at least 3 points.');
    }

    $normalizedPoints = [];
    foreach ($points as $point) {
      $normalizedPoints[] = self::normalizePoint($point);
    }

    $this->points = $normalizedPoints;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray.
   *
   * Reconstitutes a plan geometry from its persisted (or wire) shape:
   * `{"attachmentId": "<uuid>", "points": [[x, y], ...]}`.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array{attachmentId?: mixed, points?: mixed} $data the shape to reconstitute from
   *
   * @return self the reconstituted plan geometry
   */
  public static function fromArray(array $data): self
  {
    $attachmentId = $data['attachmentId'] ?? null;
    $points = $data['points'] ?? null;

    if (!is_string($attachmentId)) {
      throw InvalidValueException::because('Plan geometry "attachmentId" must be a string.');
    }

    if (!is_array($points)) {
      throw InvalidValueException::because('Plan geometry "points" must be an array.');
    }

    /** @var list<mixed> $points */
    $normalizedPoints = [];
    foreach ($points as $point) {
      $normalizedPoints[] = self::normalizePoint($point);
    }

    return new self(
      attachmentId: $attachmentId,
      points: $normalizedPoints,
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
   * Method points.
   *
   * @since 1.0.0
   *
   * @return list<array{0: float, 1: float}> the polygon points
   */
  public function points(): array
  {
    return $this->points;
  }

  /**
   * Method toArray.
   *
   * Serializes to the persisted/wire shape:
   * `{"attachmentId": "<uuid>", "points": [[x, y], ...]}`.
   *
   * @since 1.0.0
   *
   * @return array{attachmentId: string, points: list<array{0: float, 1: float}>} the serialized shape
   */
  public function toArray(): array
  {
    return [
      'attachmentId' => $this->attachmentId,
      'points' => $this->points,
    ];
  }

  /**
   * Method equals.
   *
   * @since 1.0.0
   *
   * @param self $other the plan geometry to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->toArray() === $other->toArray();
  }

  /**
   * Method normalizePoint.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param mixed $point the raw point to normalize
   *
   * @return array{0: float, 1: float} the normalized point
   */
  private static function normalizePoint(mixed $point): array
  {
    if (!is_array($point) || 2 !== count($point) || !isset($point[0], $point[1])) {
      throw InvalidValueException::because('Each plan geometry point must be a [x, y] pair.');
    }

    $x = $point[0];
    $y = $point[1];

    if (!is_int($x) && !is_float($x) || !is_int($y) && !is_float($y)) {
      throw InvalidValueException::because('Plan geometry coordinates must be numeric.');
    }

    $x = (float) $x;
    $y = (float) $y;

    if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
      throw InvalidValueException::because('Plan geometry coordinates must be normalized between 0 and 1.');
    }

    return [$x, $y];
  }
  // #endregion
}
