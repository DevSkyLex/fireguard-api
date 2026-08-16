<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\PlanGeometry;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PlanGeometryTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanGeometry::class)]
final class PlanGeometryTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testConstructAcceptsAValidTriangle(): void
  {
    $geometry = new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);

    self::assertSame(self::ATTACHMENT_ID, $geometry->attachmentId());
    self::assertSame([[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]], $geometry->points());
  }

  #[Test]
  public function testConstructRejectsAnInvalidAttachmentId(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanGeometry('not-a-uuid', [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);
  }

  #[Test]
  public function testConstructRejectsFewerThanThreePoints(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1]]);
  }

  #[Test]
  public function testConstructRejectsAPointMissingACoordinate(): void
  {
    $this->expectException(InvalidValueException::class);

    /** @phpstan-ignore-next-line argument.type intentionally malformed input under test */
    new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [0.4]]);
  }

  #[Test]
  public function testConstructRejectsANonNumericCoordinate(): void
  {
    $this->expectException(InvalidValueException::class);

    /** @phpstan-ignore-next-line argument.type intentionally malformed input under test */
    new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], ['x', 0.4]]);
  }

  #[Test]
  public function testConstructRejectsACoordinateBelowZero(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanGeometry(self::ATTACHMENT_ID, [[-0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);
  }

  #[Test]
  public function testConstructRejectsACoordinateAboveOne(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [1.1, 0.4]]);
  }

  #[Test]
  public function testConstructAcceptsBoundaryCoordinatesZeroAndOne(): void
  {
    $geometry = new PlanGeometry(self::ATTACHMENT_ID, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]]);

    self::assertSame([[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]], $geometry->points());
  }

  #[Test]
  public function testToArrayRoundTripsThroughFromArray(): void
  {
    $geometry = new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);

    $reconstituted = PlanGeometry::fromArray($geometry->toArray());

    self::assertTrue($geometry->equals($reconstituted));
    self::assertSame($geometry->toArray(), $reconstituted->toArray());
  }

  #[Test]
  public function testFromArrayRejectsAMissingPointsKey(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanGeometry::fromArray(['attachmentId' => self::ATTACHMENT_ID]);
  }

  #[Test]
  public function testFromArrayRejectsANonStringAttachmentId(): void
  {
    $this->expectException(InvalidValueException::class);

    /** @phpstan-ignore-next-line argument.type intentionally malformed input under test */
    PlanGeometry::fromArray(['attachmentId' => 42, 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]]);
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentPoints(): void
  {
    $first = new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);
    $second = new PlanGeometry(self::ATTACHMENT_ID, [[0.2, 0.1], [0.4, 0.1], [0.4, 0.4]]);

    self::assertFalse($first->equals($second));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentAttachmentId(): void
  {
    $first = new PlanGeometry(self::ATTACHMENT_ID, [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);
    $second = new PlanGeometry('550e8400-e29b-41d4-a716-446655440099', [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);

    self::assertFalse($first->equals($second));
  }
}
