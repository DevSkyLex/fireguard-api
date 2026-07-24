<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\PlanKey;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PlanKey.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanKey::class)]
final class PlanKeyTest extends TestCase
{
  #[Test]
  public function testTrimsAndKeepsValue(): void
  {
    $key = new PlanKey('  pro_max  ');

    self::assertSame('pro_max', (string) $key);
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanKey('a');
  }

  #[Test]
  public function testRejectsInvalidCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new PlanKey('Pro Plan');
  }

  #[Test]
  public function testEqualsComparesValue(): void
  {
    $left = new PlanKey('pro');
    $right = new PlanKey('pro');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new PlanKey('free')));
  }
}
