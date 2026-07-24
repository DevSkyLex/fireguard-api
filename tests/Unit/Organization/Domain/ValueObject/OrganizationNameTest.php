<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

/**
 * Test OrganizationName.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationName::class)]
final class OrganizationNameTest extends TestCase
{
  #[Test]
  public function testTrimsAndKeepsValue(): void
  {
    $name = new OrganizationName('  Acme Corp  ');

    self::assertSame('Acme Corp', (string) $name);
  }

  #[Test]
  public function testRejectsEmptyValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationName('   ');
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationName('A');
  }

  #[Test]
  public function testRejectsTooLongValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationName(str_repeat('a', 121));
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new OrganizationName('Acme');
    $right = new OrganizationName('Acme');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationName('Other')));
  }
}
