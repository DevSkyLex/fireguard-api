<?php

declare(strict_types=1);

namespace Messaging\Domain\Service;

use function array_map;
use function array_unique;
use function array_values;
use function preg_match_all;
use function rtrim;

/**
 * Service UrlExtractor.
 *
 * Extracts unique `http(s)://` URLs from a message body — used to persist
 * `messaging_message_link` rows when a message is created or edited (see
 * `PostMessageHandler`/`EditMessageHandler`). A simple regex over the whole
 * (sanitized, still-HTML) body, mirroring
 * {@see MentionExtractor::extract()}'s shape and its "keep it simple" scope:
 * it matches a URL wherever it appears — visible text or an `href`
 * attribute alike — and trims common trailing sentence/markup punctuation a
 * URL is rarely meant to include (`.`, `,`, `)`, `]`, `"`, …). Pure domain
 * service: no network fetch, no link-preview resolution.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UrlExtractor
{
  // #region Constants
  /**
   * Trailing characters trimmed off a matched URL — punctuation a sentence
   * or HTML attribute commonly appends right after a URL, never part of it.
   */
  private const string TRAILING_PUNCTUATION = ".,;:!?)]}'\"";
  // #endregion

  // #region Methods
  /**
   * Method extract.
   *
   * Extracts the unique, order-preserved `http(s)://` URLs found in a
   * message body.
   *
   * @since 1.0.0
   *
   * @param string $body the message body
   *
   * @return list<string> the extracted URLs
   */
  public function extract(string $body): array
  {
    if (1 > preg_match_all('/https?:\/\/[^\s"\'<>]+/i', $body, $matches)) {
      return [];
    }

    $urls = array_map(
      static fn (string $url): string => rtrim($url, self::TRAILING_PUNCTUATION),
      $matches[0],
    );

    return array_values(array_unique($urls));
  }
  // #endregion
}
