<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\PlanCalibration;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use const INF;
use const NAN;

/**
 * Test PlanCalibrationTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanCalibration::class)]
final class PlanCalibrationTest extends TestCase
{
  #[Test]
  public function testRoundTripsMetricTransformation(): void
  {
    $data = ['widthMeters' => 20.0, 'rotationDegrees' => 90.0, 'offsetXMeters' => -5.0, 'offsetZMeters' => 3.0];
    self::assertSame($data, PlanCalibration::fromArray($data)->toArray());
  }

  #[Test]
  #[DataProvider('invalidData')]
  public function testRejectsInvalidScaleAndOffsets(float $width, float $rotation, float $offsetX, float $offsetZ): void
  {
    $this->expectException(InvalidValueException::class);
    new PlanCalibration($width, $rotation, $offsetX, $offsetZ);
  }

  /**
   * @return iterable<string, array{float, float, float, float}>
   */
  public static function invalidData(): iterable
  {
    yield 'zero width' => [0.0, 0.0, 0.0, 0.0];
    yield 'negative width' => [-1.0, 0.0, 0.0, 0.0];
    yield 'infinite width' => [INF, 0.0, 0.0, 0.0];
    yield 'nan width' => [NAN, 0.0, 0.0, 0.0];
    yield 'rotation out of range' => [10.0, 361.0, 0.0, 0.0];
    yield 'infinite offset' => [10.0, 0.0, INF, 0.0];
  }
}
