<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\PlanPosition;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PlanPositionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanPosition::class)]
final class PlanPositionTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testConstructAcceptsAValidPosition(): void
  {
    $position = new PlanPosition(self::ATTACHMENT_ID, 0.42, 0.17);

    self::assertSame(self::ATTACHMENT_ID, $position->attachmentId());
    self::assertSame(0.42, $position->x());
    self::assertSame(0.17, $position->y());
  }

  #[Test]
  public function testConstructRejectsAnInvalidAttachmentId(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanPosition('not-a-uuid', 0.1, 0.1);
  }

  #[Test]
  public function testConstructRejectsAnXBelowZero(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanPosition(self::ATTACHMENT_ID, -0.1, 0.1);
  }

  #[Test]
  public function testConstructRejectsAnXAboveOne(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanPosition(self::ATTACHMENT_ID, 1.1, 0.1);
  }

  #[Test]
  public function testConstructRejectsAYBelowZero(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanPosition(self::ATTACHMENT_ID, 0.1, -0.1);
  }

  #[Test]
  public function testConstructRejectsAYAboveOne(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanPosition(self::ATTACHMENT_ID, 0.1, 1.1);
  }

  #[Test]
  public function testConstructAcceptsBoundaryCoordinatesZeroAndOne(): void
  {
    $position = new PlanPosition(self::ATTACHMENT_ID, 0.0, 1.0);

    self::assertSame(0.0, $position->x());
    self::assertSame(1.0, $position->y());
  }

  #[Test]
  public function testToArrayRoundTripsThroughFromArray(): void
  {
    $position = new PlanPosition(self::ATTACHMENT_ID, 0.42, 0.17);

    $reconstituted = PlanPosition::fromArray($position->toArray());

    self::assertTrue($position->equals($reconstituted));
    self::assertSame($position->toArray(), $reconstituted->toArray());
  }

  #[Test]
  public function testFromArrayRejectsAMissingXKey(): void
  {
    $this->expectException(InvalidValueException::class);

    PlanPosition::fromArray(['attachmentId' => self::ATTACHMENT_ID, 'y' => 0.1]);
  }

  #[Test]
  public function testFromArrayRejectsANonStringAttachmentId(): void
  {
    $this->expectException(InvalidValueException::class);

    /** @phpstan-ignore-next-line argument.type intentionally malformed input under test */
    PlanPosition::fromArray(['attachmentId' => 42, 'x' => 0.1, 'y' => 0.1]);
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentCoordinates(): void
  {
    $first = new PlanPosition(self::ATTACHMENT_ID, 0.1, 0.1);
    $second = new PlanPosition(self::ATTACHMENT_ID, 0.2, 0.1);

    self::assertFalse($first->equals($second));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentAttachmentId(): void
  {
    $first = new PlanPosition(self::ATTACHMENT_ID, 0.1, 0.1);
    $second = new PlanPosition('550e8400-e29b-41d4-a716-446655440099', 0.1, 0.1);

    self::assertFalse($first->equals($second));
  }
}
