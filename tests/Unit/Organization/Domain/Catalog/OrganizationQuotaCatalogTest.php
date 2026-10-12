<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Catalog;

use Organization\Domain\Catalog\OrganizationQuotaCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_column;

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
      array_column(\Tests\Support\OrganizationCatalogTestFactory::quota()->definitions(), 'key'),
    );
  }

  #[Test]
  public function testRegistryResolvesKnownKeysAndDeniesUnknownKeys(): void
  {
    $catalog = \Tests\Support\OrganizationCatalogTestFactory::quota();
    self::assertSame('members', $catalog->get('members')->key);
    $this->expectException(InvalidValueException::class);
    $catalog->get('unknown');
  }

  #[Test]
  public function testDescriptionComesFromTheOwnerManifest(): void
  {
    self::assertSame('Maximum number of organization members', \Tests\Support\OrganizationCatalogTestFactory::quota()->get('members')->description);
  }

  #[Test]
  public function testNormalizeLimitsKeepsValidIntegers(): void
  {
    $normalized = OrganizationQuotaCatalog::normalizeLimits(['members' => 10, 'facilities' => 0]);

    self::assertSame(['members' => 10, 'facilities' => 0], $normalized);
  }

  #[Test]
  public function testRegisteredCatalogRejectsAnUnknownNewKey(): void
  {
    $this->expectException(InvalidValueException::class);

    \Tests\Support\OrganizationCatalogTestFactory::quota()->normalizeLimits(['unknown' => 10]);
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
