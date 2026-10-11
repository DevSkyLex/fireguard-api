<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use function array_keys;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Class MergePatchDocument.
 *
 * Decodes supplied fields without confusing omitted values with explicit nulls.
 *
 * @category Contract
 */
final readonly class MergePatchDocument
{
  // #region Methods
  /**
   * Method fields.
   *
   * Retains the historical empty result for malformed JSON or numeric object keys.
   *
   * @access public
   *
   * @param string $contents the raw JSON document
   *
   * @return array<string, mixed> the supplied field values
   */
  public static function fields(string $contents): array
  {
    $fields = json_decode($contents, true);
    if (!is_array($fields)) {
      return [];
    }
    foreach (array_keys($fields) as $key) {
      if (!is_string($key)) {
        return [];
      }
    }

    /** @var array<string, mixed> $fields */
    return $fields;
  }
  // #endregion
}
