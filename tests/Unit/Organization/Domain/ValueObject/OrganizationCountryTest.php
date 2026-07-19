<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationCountry;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(OrganizationCountry::class)]
final class OrganizationCountryTest extends TestCase
{
  #[Test]
  public function testNormalizesToUppercase(): void
  {
    $country = new OrganizationCountry('fr');

    self::assertSame('FR', (string) $country);
  }

  #[Test]
  public function testTrimsSurroundingWhitespace(): void
  {
    $country = new OrganizationCountry('  US  ');

    self::assertSame('US', (string) $country);
  }

  #[Test]
  public function testRejectsWrongLength(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationCountry('FRA');
  }

  #[Test]
  public function testRejectsNonAlphaCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationCountry('F1');
  }

  #[Test]
  public function testRejectsUnrecognizedCode(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationCountry('ZZ');
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new OrganizationCountry('fr');
    $right = new OrganizationCountry('FR');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationCountry('DE')));
  }
}
