<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Service;

use Messaging\Domain\Service\MentionExtractor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test MentionExtractorTest.
 *
 * @category Domain Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MentionExtractor::class)]
final class MentionExtractorTest extends TestCase
{
  private const string MEMBER_A = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_B = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testExtractReturnsUniqueMentionedMemberIds(): void
  {
    $body = sprintf(
      'Hey @{%s} and @{%s}, can you check this? Also @{%s} again.',
      self::MEMBER_A,
      self::MEMBER_B,
      self::MEMBER_A,
    );

    $mentions = new MentionExtractor()->extract($body);

    self::assertSame([self::MEMBER_A, self::MEMBER_B], $mentions);
  }

  #[Test]
  public function testExtractIgnoresMalformedTokens(): void
  {
    $mentions = new MentionExtractor()->extract('Not a mention: @{not-a-uuid} or plain @text.');

    self::assertSame([], $mentions);
  }

  #[Test]
  public function testExtractReturnsEmptyListForPlainText(): void
  {
    self::assertSame([], new MentionExtractor()->extract('Nothing to see here.'));
  }

  /**
   * The form every real caller actually produces.
   *
   * Both call sites sanitize before dispatching, and Symfony's
   * `StringSanitizer` rewrites every `@` in a text node to `&#64;` — so this,
   * not the bare `@`, is what the extractor is handed in production.
   */
  #[Test]
  public function testExtractReadsTheSanitizedTokenForm(): void
  {
    $body = sprintf('<p>Hey &#64;{%s}, can you check this?</p>', self::MEMBER_A);

    self::assertSame([self::MEMBER_A], new MentionExtractor()->extract($body));
  }

  #[Test]
  public function testExtractDeduplicatesAcrossBothSpellings(): void
  {
    $body = sprintf('@{%s} then &#64;{%s}', self::MEMBER_A, self::MEMBER_A);

    self::assertSame([self::MEMBER_A], new MentionExtractor()->extract($body));
  }
}
