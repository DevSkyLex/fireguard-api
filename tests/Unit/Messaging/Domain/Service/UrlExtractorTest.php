<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Service;

use Messaging\Domain\Service\UrlExtractor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UrlExtractorTest.
 *
 * @category Domain Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UrlExtractor::class)]
final class UrlExtractorTest extends TestCase
{
  #[Test]
  public function testExtractReturnsASingleHttpsUrl(): void
  {
    $urls = new UrlExtractor()->extract('Check this out: https://example.com/report');

    self::assertSame(['https://example.com/report'], $urls);
  }

  #[Test]
  public function testExtractReturnsAnHttpUrlToo(): void
  {
    $urls = new UrlExtractor()->extract('Legacy link: http://intranet.local/doc');

    self::assertSame(['http://intranet.local/doc'], $urls);
  }

  #[Test]
  public function testExtractReturnsMultipleUniqueUrlsInOrder(): void
  {
    $body = 'See https://example.com/a and https://example.com/b, then https://example.com/a again.';

    $urls = new UrlExtractor()->extract($body);

    self::assertSame(['https://example.com/a', 'https://example.com/b'], $urls);
  }

  #[Test]
  public function testExtractTrimsTrailingSentencePunctuation(): void
  {
    $urls = new UrlExtractor()->extract('Please read https://example.com/report.');

    self::assertSame(['https://example.com/report'], $urls);
  }

  #[Test]
  public function testExtractTrimsTrailingClosingParenthesis(): void
  {
    $urls = new UrlExtractor()->extract('(see https://example.com/report)');

    self::assertSame(['https://example.com/report'], $urls);
  }

  #[Test]
  public function testExtractMatchesAUrlInsideAnAnchorHrefAttribute(): void
  {
    $urls = new UrlExtractor()->extract('<p>Read the <a href="https://example.com/report">report</a> now.</p>');

    self::assertSame(['https://example.com/report'], $urls);
  }

  #[Test]
  public function testExtractReturnsEmptyListForPlainText(): void
  {
    self::assertSame([], new UrlExtractor()->extract('Nothing to see here.'));
  }

  #[Test]
  public function testExtractIgnoresANonHttpScheme(): void
  {
    self::assertSame([], new UrlExtractor()->extract('Contact ftp://files.example.com or mailto:a@b.com'));
  }
}
