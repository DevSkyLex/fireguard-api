<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\TeamName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

#[CoversClass(TeamName::class)]
final class TeamNameTest extends TestCase
{
  #[Test]
  public function testTrimsSurroundingWhitespace(): void
  {
    $name = new TeamName('  Field crew A  ');

    self::assertSame('Field crew A', (string) $name);
  }

  #[Test]
  public function testAllowsSpacesUnlikeRoleSlug(): void
  {
    $name = new TeamName('Rooftop Inspection Crew');

    self::assertSame('Rooftop Inspection Crew', (string) $name);
  }

  #[Test]
  public function testRejectsEmptyValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new TeamName('   ');
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new TeamName('A');
  }

  #[Test]
  public function testRejectsTooLongValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new TeamName(str_repeat('a', 81));
  }

  #[Test]
  public function testRejectsControlCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new TeamName("Field crew\x00A");
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new TeamName('Field crew A');
    $right = new TeamName('  Field crew A');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new TeamName('Field crew B')));
  }
}
