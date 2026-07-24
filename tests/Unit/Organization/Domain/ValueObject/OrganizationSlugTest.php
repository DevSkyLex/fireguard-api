<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationSlug;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationSlug.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationSlug::class)]
final class OrganizationSlugTest extends TestCase
{
  #[Test]
  public function testNormalizesToLowercase(): void
  {
    $slug = new OrganizationSlug('  Acme-Corp  ');

    self::assertSame('acme-corp', (string) $slug);
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationSlug('ab');
  }

  #[Test]
  public function testRejectsInvalidCharacters(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationSlug('acme corp');
  }

  #[Test]
  public function testFromNameSlugifiesFreeText(): void
  {
    $slug = OrganizationSlug::fromName('Acme  Corp!');

    self::assertSame('acme-corp', (string) $slug);
  }

  #[Test]
  public function testFromNameFallsBackToDefaultWhenEmpty(): void
  {
    $slug = OrganizationSlug::fromName('!!!');

    self::assertSame('organization', (string) $slug);
  }

  #[Test]
  public function testEqualsComparesNormalizedValue(): void
  {
    $left = new OrganizationSlug('acme');
    $right = new OrganizationSlug('ACME');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationSlug('other')));
  }
}
