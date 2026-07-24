<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationRegistrationNumber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationRegistrationNumber.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRegistrationNumber::class)]
final class OrganizationRegistrationNumberTest extends TestCase
{
  #[Test]
  public function testNormalizesToUppercaseAndTrims(): void
  {
    $number = new OrganizationRegistrationNumber('  rcs-123  ');

    self::assertSame('RCS-123', (string) $number);
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegistrationNumber('AB');
  }

  #[Test]
  public function testRejectsUnsupportedCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegistrationNumber('RCS_123');
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new OrganizationRegistrationNumber('rcs-123');
    $right = new OrganizationRegistrationNumber('RCS-123');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationRegistrationNumber('RCS-999')));
  }
}
