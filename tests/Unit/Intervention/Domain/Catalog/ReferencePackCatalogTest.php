<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Catalog;

use Intervention\Domain\Catalog\ReferencePackCatalog;
use Intervention\Domain\ValueObject\ReferencePack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test ReferencePackCatalogTest.
 *
 * @category Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ReferencePackCatalogTest extends TestCase
{
  #[Test]
  public function itExposesAtLeastOnePackAndAStableDefault(): void
  {
    $catalog = new ReferencePackCatalog();

    self::assertNotEmpty($catalog->all());
    self::assertSame($catalog->all()[0]->id, $catalog->defaultPack()->id);
  }

  #[Test]
  public function itFindsAKnownPackById(): void
  {
    $catalog = new ReferencePackCatalog();
    $default = $catalog->defaultPack();

    $found = $catalog->find($default->id);

    self::assertInstanceOf(ReferencePack::class, $found);
    self::assertSame($default->id, $found->id);
  }

  #[Test]
  public function itReturnsNullForAnUnknownPack(): void
  {
    self::assertNull((new ReferencePackCatalog())->find('does-not-exist'));
  }
}
