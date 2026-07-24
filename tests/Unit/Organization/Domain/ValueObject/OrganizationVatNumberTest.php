<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationVatNumber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationVatNumber.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationVatNumber::class)]
final class OrganizationVatNumberTest extends TestCase
{
  #[Test]
  public function testNormalizesToUppercaseAndTrims(): void
  {
    $vat = new OrganizationVatNumber('  fr12345  ');

    self::assertSame('FR12345', (string) $vat);
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationVatNumber('AB');
  }

  #[Test]
  public function testRejectsUnsupportedCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationVatNumber('FR@12345');
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new OrganizationVatNumber('fr12345');
    $right = new OrganizationVatNumber('FR12345');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationVatNumber('DE99999')));
  }
}
