<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use InvalidArgumentException;

use function is_array;
use function is_string;

/**
 * Class ParsedHeaderValue
 *
 * Validates transport scalars without rewriting conditional-request syntax.
 * HTTP bridges retain ownership of their framework exception and header bag.
 *
 * @category HTTP Contract
 */
final readonly class ParsedHeaderValue
{
  // #region Methods
  /**
   * Method normalize
   *
   * String lists preserve their order while associative keys are discarded.
   * Null remains explicit and invalid values fail before reaching a header bag.
   *
   * @access public
   *
   * @param mixed $value a present parsed header value
   *
   * @return string|list<string>|null the validated header value
   *
   * @throws InvalidArgumentException when the value contains any non-string entry
   */
  public static function normalize(mixed $value): string|array|null
  {
    if (null === $value || is_string($value)) {
      return $value;
    }
    if (!is_array($value)) {
      throw new InvalidArgumentException('Invalid header value.');
    }

    $entries = [];
    foreach ($value as $entry) {
      if (!is_string($entry)) {
        throw new InvalidArgumentException('Invalid header value.');
      }
      $entries[] = $entry;
    }

    return $entries;
  }
  // #endregion
}
