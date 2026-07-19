<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Stringable;

use function grapheme_strlen;
use function mb_strlen;
use function preg_match;
use function trim;

/**
 * ValueObject MessagingEmoji.
 *
 * A single reaction emoji. This column is rendered directly by clients, so
 * arbitrary text must never reach it: the value MUST be exactly ONE
 * user-perceived character (one extended grapheme cluster — this is what
 * lets a ZWJ family sequence, a flag, a skin-tone-modified emoji, or a
 * keycap sequence like "3️⃣" count as a single reaction while rejecting a
 * pasted sentence or a string of several emoji) AND contain at least one
 * codepoint outside the printable ASCII range, which rejects a bare letter,
 * digit or punctuation mark (`"a"`, `"5"`, `"#"` all form a single grapheme
 * too, but are not plausible emoji on their own — only combined with a
 * variation selector/combining enclosing keycap mark, both non-ASCII, does a
 * digit become a legitimate keycap emoji). `grapheme_strlen()` is provided by
 * `symfony/polyfill-intl-grapheme` even where `ext-intl` is absent, so this
 * validation never depends on the runtime's ICU availability. The 32
 * character bound mirrors `messaging_reactions.emoji` (`varchar(32)`).
 *
 * @category ValueObject
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingEmoji implements Stringable
{
  // #region Constants
  /**
   * Constant MAX_LENGTH.
   *
   * Mirrors the `messaging_reactions.emoji` column (`varchar(32)`).
   */
  private const int MAX_LENGTH = 32;

  /**
   * Constant NON_ASCII_PATTERN.
   *
   * Matches any codepoint outside the printable ASCII range.
   */
  private const string NON_ASCII_PATTERN = '/[\x{80}-\x{10FFFF}]/u';
  // #endregion

  // #region Properties
  /**
   * Property value.
   *
   * The validated, trimmed emoji grapheme.
   *
   * @since 1.1.0
   */
  public string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Use {@see self::fromString()} instead of instantiating directly.
   *
   * @since 1.1.0
   *
   * @param string $value the validated emoji value
   */
  private function __construct(string $value)
  {
    $this->value = $value;
  }

  /**
   * Method __toString.
   *
   * @since 1.1.0
   *
   * @return string the validated emoji value
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Trims and validates a raw reaction emoji.
   *
   * @since 1.1.0
   *
   * @param string $value the raw emoji value
   *
   * @return self the validated emoji
   */
  public static function fromString(string $value): self
  {
    $trimmed = trim($value);

    if ('' === $trimmed) {
      throw new MessagingValidationException('The reaction emoji must not be blank.');
    }

    if (mb_strlen($trimmed) > self::MAX_LENGTH) {
      throw new MessagingValidationException('The reaction emoji must not exceed 32 characters.');
    }

    if (1 !== grapheme_strlen($trimmed)) {
      throw new MessagingValidationException('The reaction emoji must be a single character.');
    }

    if (1 !== preg_match(self::NON_ASCII_PATTERN, $trimmed)) {
      throw new MessagingValidationException('The reaction emoji must be a plausible emoji character.');
    }

    return new self($trimmed);
  }
  // #endregion
}
