<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function abs;
use function count;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;

/**
 * ValueObject PlanGeometry.
 *
 * Binds a facility (a spatial zone) to a polygon drawn over an ancestor's
 * floor plan attachment. Coordinates are normalized image-space [0, 1]
 * fractions of the plan's width/height, not pixels, so the shape survives
 * the plan being re-rendered at a different resolution.
 *
 * @category ValueObject
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
  /**
   * Property attachmentId
   */
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

    if (count($points) > 1000) {
      throw InvalidValueException::because('Plan geometry must have at most 1000 points.');
    }

    $normalizedPoints = [];
    foreach ($points as $point) {
      $normalizedPoints[] = self::normalizePoint($point);
    }

    self::assertSimplePolygon($normalizedPoints);
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
   * Method fromPersistedArray.
   *
   * Returns null for legacy data that cannot safely be rendered.
   *
   * @since 1.0.0
   *
   * @param array{attachmentId?: mixed, points?: mixed} $data the persisted shape
   */
  public static function fromPersistedArray(array $data): ?self
  {
    try {
      return self::fromArray($data);
    } catch (InvalidValueException) {
      return null;
    }
  }

  /**
   * Method isUsable.
   *
   * @since 1.0.0
   *
   * @param array{attachmentId?: mixed, points?: mixed} $data the persisted shape
   */
  public static function isUsable(array $data): bool
  {
    return null !== self::fromPersistedArray($data);
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

    if (!is_finite($x) || !is_finite($y) || $x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
      throw InvalidValueException::because('Plan geometry coordinates must be normalized between 0 and 1.');
    }

    return [$x, $y];
  }

  /**
   * Method assertSimplePolygon.
   *
   * Adjacent edges may share their endpoint; other crossings and touches
   * would make the enclosed area ambiguous for editing and extrusion.
   *
   * @since 1.0.0
   *
   * @param list<array{0: float, 1: float}> $points the normalized polygon
   */
  private static function assertSimplePolygon(array $points): void
  {
    $count = count($points);
    $area = 0.0;
    for ($i = 0; $i < $count; ++$i) {
      $a = $points[$i];
      $b = $points[($i + 1) % $count];
      if ($a === $b) {
        throw InvalidValueException::because('Plan geometry must not contain repeated adjacent points.');
      }
      $area += $a[0] * $b[1] - $b[0] * $a[1];
      for ($j = $i + 1; $j < $count; ++$j) {
        if ($j === $i + 1 || (0 === $i && $j === $count - 1)) {
          continue;
        }
        if (self::segmentsIntersect($a, $b, $points[$j], $points[($j + 1) % $count])) {
          throw InvalidValueException::because('Plan geometry must not intersect itself.');
        }
      }
    }
    if (abs($area) <= 1.0e-10) {
      throw InvalidValueException::because('Plan geometry must enclose a non-zero area.');
    }
  }

  /**
   * Method segmentsIntersect.
   *
   * @since 1.0.0
   *
   * @param array{0: float, 1: float} $a
   * @param array{0: float, 1: float} $b
   * @param array{0: float, 1: float} $c
   * @param array{0: float, 1: float} $d
   */
  private static function segmentsIntersect(array $a, array $b, array $c, array $d): bool
  {
    $abc = self::cross($a, $b, $c);
    $abd = self::cross($a, $b, $d);
    $cda = self::cross($c, $d, $a);
    $cdb = self::cross($c, $d, $b);

    return (($abc > 0.0 && $abd < 0.0 || $abc < 0.0 && $abd > 0.0) && ($cda > 0.0 && $cdb < 0.0 || $cda < 0.0 && $cdb > 0.0))
      || (abs($abc) <= 1.0e-10 && self::onSegment($a, $b, $c))
      || (abs($abd) <= 1.0e-10 && self::onSegment($a, $b, $d))
      || (abs($cda) <= 1.0e-10 && self::onSegment($c, $d, $a))
      || (abs($cdb) <= 1.0e-10 && self::onSegment($c, $d, $b));
  }

  /**
   * Method cross.
   *
   * @since 1.0.0
   *
   * @param array{0: float, 1: float} $p
   * @param array{0: float, 1: float} $q
   * @param array{0: float, 1: float} $r
   */
  private static function cross(array $p, array $q, array $r): float
  {
    return ($q[0] - $p[0]) * ($r[1] - $p[1]) - ($q[1] - $p[1]) * ($r[0] - $p[0]);
  }

  /**
   * Method onSegment.
   *
   * @since 1.0.0
   *
   * @param array{0: float, 1: float} $p
   * @param array{0: float, 1: float} $q
   * @param array{0: float, 1: float} $r
   */
  private static function onSegment(array $p, array $q, array $r): bool
  {
    return $r[0] >= min($p[0], $q[0]) - 1.0e-10 && $r[0] <= max($p[0], $q[0]) + 1.0e-10
      && $r[1] >= min($p[1], $q[1]) - 1.0e-10 && $r[1] <= max($p[1], $q[1]) + 1.0e-10;
  }
  // #endregion
}
