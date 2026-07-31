<?php

declare(strict_types=1);

namespace Messaging\Domain\Service;

use function array_unique;
use function array_values;
use function preg_match_all;

/**
 * Service MentionExtractor.
 *
 * Extracts `@{memberUuid}` mention tokens from a message body — the exact
 * regex used by
 * {@see \Intervention\Application\UseCase\Command\Activity\AddInterventionComment\AddInterventionCommentHandler::extractMentions()},
 * kept in lock-step on purpose so the token form survives rich-text
 * sanitization identically across both modules.
 *
 * The `&#64;` alternative is not defensive, it is the only form that ever
 * arrives here. Both callers sanitize before dispatching the command, and
 * {@see \Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer::REPLACEMENTS}
 * rewrites every `@` in a text node to `&#64;` — so a pattern matching the bare
 * `@` alone silently extracted nothing, and no mention has ever notified anyone.
 * Both spellings are accepted rather than only the escaped one, because the
 * extractor is a pure function that must also hold for an unsanitized body.
 *
 * Pure domain service: no
 * membership validation happens here (member ids come from user input and
 * are checked downstream by {@see \Messaging\Application\Service\MessagingNotificationService}).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MentionExtractor
{
  // #region Methods
  /**
   * Method extract.
   *
   * Extracts the unique member identifiers mentioned in a message body.
   *
   * @since 1.0.0
   *
   * @param string $body the message body
   *
   * @return list<string> the mentioned member identifiers
   */
  public function extract(string $body): array
  {
    if (1 > preg_match_all('/(?:@|&#64;)\{([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\}/', $body, $matches)) {
      return [];
    }

    return array_values(array_unique($matches[1]));
  }
  // #endregion
}
