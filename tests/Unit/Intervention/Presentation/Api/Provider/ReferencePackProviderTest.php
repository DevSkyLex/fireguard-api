<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use Intervention\Domain\Catalog\ReferencePackCatalog;
use Intervention\Presentation\Api\Dto\Output\ReferencePackOutput;
use Intervention\Presentation\Api\Provider\ReferencePackProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Test ReferencePackProviderTest.
 *
 * @category Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ReferencePackProviderTest extends TestCase
{
  #[Test]
  public function itListsEveryCatalogPack(): void
  {
    $catalog = new ReferencePackCatalog();
    $provider = new ReferencePackProvider($catalog);

    $result = $provider->provide(new GetCollection());

    self::assertIsArray($result);
    self::assertCount(count($catalog->all()), $result);
    self::assertSame($catalog->defaultPack()->id, $result[0]->id);
  }

  #[Test]
  public function itReturnsASinglePackById(): void
  {
    $catalog = new ReferencePackCatalog();
    $provider = new ReferencePackProvider($catalog);

    $output = $provider->provide(new Get(), ['id' => $catalog->defaultPack()->id]);

    self::assertInstanceOf(ReferencePackOutput::class, $output);
    self::assertSame($catalog->defaultPack()->id, $output->id);
  }

  #[Test]
  public function itThrowsWhenThePackIsUnknown(): void
  {
    $provider = new ReferencePackProvider(new ReferencePackCatalog());

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => 'unknown']);
  }
}
