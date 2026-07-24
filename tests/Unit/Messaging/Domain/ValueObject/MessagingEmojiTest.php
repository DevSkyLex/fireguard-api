<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\MessagingEmoji;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test MessagingEmojiTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingEmoji::class)]
final class MessagingEmojiTest extends TestCase
{
  /**
   * @return iterable<string, array{0: string}>
   */
  public static function plausibleEmojiProvider(): iterable
  {
    yield 'simple emoji' => ["\u{1F600}"]; // 😀
    yield 'ZWJ family sequence' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}"]; // 👨‍👩‍👧‍👦
    yield 'keycap sequence' => ["3\u{FE0F}\u{20E3}"]; // 3️⃣
    yield 'flag (regional indicator pair)' => ["\u{1F1EB}\u{1F1F7}"]; // 🇫🇷
    yield 'skin-tone modified' => ["\u{1F44D}\u{1F3FD}"]; // 👍🏽
    yield 'padded with whitespace' => ["  \u{1F44B}  "]; // 👋
  }

  #[Test]
  #[DataProvider('plausibleEmojiProvider')]
  public function testFromStringAcceptsAPlausibleSingleEmojiGrapheme(string $raw): void
  {
    $emoji = MessagingEmoji::fromString($raw);

    self::assertNotSame('', $emoji->value);
    self::assertSame((string) $emoji, $emoji->value);
  }

  /**
   * @return iterable<string, array{0: string}>
   */
  public static function implausibleEmojiProvider(): iterable
  {
    yield 'blank' => [''];
    yield 'whitespace only' => ['   '];
    yield 'plain ascii letter' => ['a'];
    yield 'plain ascii digit' => ['5'];
    yield 'plain ascii punctuation' => ['#'];
    yield 'html-ish text' => ['<script>'];
    yield 'multiple emoji' => ["\u{1F642}\u{1F642}"]; // 🙂🙂 — two graphemes
    yield 'a whole sentence' => ['Hello team'];
  }

  #[Test]
  #[DataProvider('implausibleEmojiProvider')]
  public function testFromStringRejectsAnythingThatIsNotAPlausibleSingleEmoji(string $raw): void
  {
    $this->expectException(MessagingValidationException::class);

    MessagingEmoji::fromString($raw);
  }

  #[Test]
  public function testFromStringRejectsAValueLongerThanThirtyTwoCharacters(): void
  {
    // 33 plain ASCII characters: fails the length bound before the grapheme
    // check would even be reached.
    $this->expectException(MessagingValidationException::class);

    MessagingEmoji::fromString(str_repeat('a', 33));
  }
}
