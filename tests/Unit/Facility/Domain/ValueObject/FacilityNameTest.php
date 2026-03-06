<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

#[CoversClass(FacilityName::class)]
final class FacilityNameTest extends TestCase
{
  #[Test]
  public function testConstructorThrowsOnEmptyString(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility name cannot be empty.');

    new FacilityName('');
  }

  #[Test]
  public function testConstructorThrowsOnWhitespaceOnly(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility name cannot be empty.');

    new FacilityName('   ');
  }

  #[Test]
  public function testConstructorThrowsOnSingleCharacterAfterTrimming(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility name must be between 2 and 120 characters.');

    new FacilityName('A');
  }

  #[Test]
  public function testConstructorThrowsOnSingleCharacterWithWhitespace(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility name must be between 2 and 120 characters.');

    new FacilityName('  B  ');
  }

  #[Test]
  public function testConstructorAcceptsMinimumLength(): void
  {
    $name = new FacilityName('AB');

    self::assertSame('AB', (string) $name);
  }

  #[Test]
  public function testConstructorAcceptsMaximumLength(): void
  {
    $value = str_repeat('A', 120);
    $name = new FacilityName($value);

    self::assertSame($value, (string) $name);
  }

  #[Test]
  public function testConstructorThrowsWhenExceedsMaximumLength(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility name must be between 2 and 120 characters.');

    new FacilityName(str_repeat('A', 121));
  }

  #[Test]
  public function testConstructorTrimsWhitespace(): void
  {
    $name = new FacilityName('  HQ Building  ');

    self::assertSame('HQ Building', (string) $name);
  }

  #[Test]
  public function testToStringReturnsValue(): void
  {
    $name = new FacilityName('Main Site');

    self::assertSame('Main Site', (string) $name);
    self::assertSame('Main Site', $name->__toString());
  }

  #[Test]
  public function testEqualsReturnsTrueForSameValue(): void
  {
    $a = new FacilityName('Site One');
    $b = new FacilityName('Site One');

    self::assertTrue($a->equals($b));
  }

  #[Test]
  public function testEqualsReturnsFalseForDifferentValue(): void
  {
    $a = new FacilityName('Site One');
    $b = new FacilityName('Site Two');

    self::assertFalse($a->equals($b));
  }

  #[Test]
  public function testEqualsIsCaseSensitive(): void
  {
    $a = new FacilityName('Main Site');
    $b = new FacilityName('main site');

    self::assertFalse($a->equals($b));
  }
}
