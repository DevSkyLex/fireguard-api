<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Dto\Output\User;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\Image\AvatarResizer;
use User\Presentation\Api\Dto\Output\User\AvatarUrls;

use function array_keys;
use function sprintf;

/**
 * Test AvatarUrlsTest.
 *
 * The per-size avatar map is derived purely from the canonical URL, so the
 * cases that matter are the ones where the URL is not the internal avatar
 * endpoint (an external identity-provider picture must never be rewritten
 * into fake `/32.webp` variants) and the cache-busting query, which has to
 * survive on every variant or a fresh upload leaves stale sizes cached.
 *
 * @category DTO Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AvatarUrls::class)]
final class AvatarUrlsTest extends TestCase
{
  /**
   * @return iterable<string, array{string}>
   */
  public static function nonAvatarUrlProvider(): iterable
  {
    yield 'external picture' => ['https://cdn.example.com/photo.jpg'];
    yield 'other endpoint' => ['https://api.fireguard.test/api/users/abc/logo'];
    yield 'avatar path segment mid-url' => ['https://api.fireguard.test/avatar/256.webp/extra'];
    yield 'blank' => [''];
  }

  #[Test]
  public function testItReturnsNullWhenNoAvatarIsSet(): void
  {
    self::assertNull(AvatarUrls::fromAvatarUrl(null));
  }

  #[Test]
  #[DataProvider('nonAvatarUrlProvider')]
  public function testItReturnsNullForAUrlOutsideTheInternalAvatarEndpoint(string $url): void
  {
    self::assertNull(AvatarUrls::fromAvatarUrl($url));
  }

  #[Test]
  public function testItDerivesEverySizeFromTheCanonicalUrl(): void
  {
    $urls = AvatarUrls::fromAvatarUrl('https://api.fireguard.test/api/users/u-1/avatar/256.webp');

    self::assertNotNull($urls);
    self::assertSame(AvatarResizer::SIZES, array_keys($urls));
    self::assertSame('https://api.fireguard.test/api/users/u-1/avatar/256.webp', $urls[256]);
    self::assertSame('https://api.fireguard.test/api/users/u-1/avatar/32.webp', $urls[32]);
  }

  #[Test]
  public function testItAcceptsTheBareAvatarEndpointWithoutASizeSuffix(): void
  {
    $urls = AvatarUrls::fromAvatarUrl('https://api.fireguard.test/api/users/u-1/avatar');

    self::assertNotNull($urls);
    self::assertCount(4, $urls);
    self::assertSame('https://api.fireguard.test/api/users/u-1/avatar/128.webp', $urls[128]);
  }

  #[Test]
  public function testItPreservesTheCacheBustingQueryOnEverySize(): void
  {
    $urls = AvatarUrls::fromAvatarUrl('https://api.fireguard.test/api/users/u-1/avatar/256.webp?v=1700000000');

    self::assertNotNull($urls);
    foreach (AvatarResizer::SIZES as $size) {
      self::assertSame(
        sprintf('https://api.fireguard.test/api/users/u-1/avatar/%d.webp?v=1700000000', $size),
        $urls[$size],
      );
    }
  }
}
