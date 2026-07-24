<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Catalog;

use Organization\Domain\Catalog\PlanPresentationCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(PlanPresentationCatalog::class)]
final class PlanPresentationCatalogTest extends TestCase
{
  #[Test]
  public function testTaglineIdForKnownPlanKeyReturnsAnIdentifier(): void
  {
    self::assertSame('plan.free.tagline', PlanPresentationCatalog::taglineIdFor('free'));
    self::assertSame('plan.pro.tagline', PlanPresentationCatalog::taglineIdFor('pro'));
    self::assertSame('plan.max.tagline', PlanPresentationCatalog::taglineIdFor('max'));
  }

  #[Test]
  public function testTaglineIdForUnknownPlanKeyReturnsNull(): void
  {
    self::assertNull(PlanPresentationCatalog::taglineIdFor('custom-enterprise'));
  }

  #[Test]
  public function testPerkIdsForKnownPlanKeyReturnsOrderedIdentifiers(): void
  {
    self::assertSame([
      'plan.pro.perks.0',
      'plan.pro.perks.1',
      'plan.pro.perks.2',
    ], PlanPresentationCatalog::perkIdsFor('pro'));
  }

  #[Test]
  public function testPerkIdsForUnknownPlanKeyReturnsEmptyList(): void
  {
    self::assertSame([], PlanPresentationCatalog::perkIdsFor('custom-enterprise'));
  }
}
