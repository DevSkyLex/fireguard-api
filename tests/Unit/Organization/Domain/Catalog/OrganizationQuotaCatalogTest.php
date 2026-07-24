<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Catalog;

use Organization\Domain\Catalog\OrganizationQuotaCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationQuotaCatalog.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationQuotaCatalog::class)]
final class OrganizationQuotaCatalogTest extends TestCase
{
  #[Test]
  public function testKeysExposesEveryResource(): void
  {
    self::assertSame(
      ['members', 'facilities', 'equipment', 'inspections'],
      OrganizationQuotaCatalog::keys(),
    );
  }

  #[Test]
  public function testIsValidRecognizesKnownAndUnknownKeys(): void
  {
    self::assertTrue(OrganizationQuotaCatalog::isValid('members'));
    self::assertFalse(OrganizationQuotaCatalog::isValid('unknown'));
  }

  #[Test]
  public function testDescriptionForReturnsEmptyForUnknownKey(): void
  {
    self::assertNotSame('', OrganizationQuotaCatalog::descriptionFor('members'));
    self::assertSame('', OrganizationQuotaCatalog::descriptionFor('unknown'));
  }

  #[Test]
  public function testNormalizeLimitsKeepsValidIntegers(): void
  {
    $normalized = OrganizationQuotaCatalog::normalizeLimits(['members' => 10, 'facilities' => 0]);

    self::assertSame(['members' => 10, 'facilities' => 0], $normalized);
  }

  #[Test]
  public function testNormalizeLimitsRejectsUnknownKey(): void
  {
    $this->expectException(InvalidValueException::class);

    OrganizationQuotaCatalog::normalizeLimits(['unknown' => 10]);
  }

  #[Test]
  public function testNormalizeLimitsRejectsNegativeLimit(): void
  {
    $this->expectException(InvalidValueException::class);

    OrganizationQuotaCatalog::normalizeLimits(['members' => -1]);
  }

  #[Test]
  public function testNormalizeLimitsRejectsNonInteger(): void
  {
    $this->expectException(InvalidValueException::class);

    OrganizationQuotaCatalog::normalizeLimits(['members' => '10']);
  }
}
