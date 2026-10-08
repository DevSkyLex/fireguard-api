<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Csv;

use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;

use function preg_match;
use function str_starts_with;
use function substr;

/**
 * Class SpreadsheetSafeTextAdapter
 *
 * Protects text cells at the beginning of their spreadsheet interpretation and
 * doubles existing leading apostrophes so marked exports remain reversible.
 *
 * @category Adapter
 */
final readonly class SpreadsheetSafeTextAdapter implements SpreadsheetSafeTextPort
{
  // #region Methods
  /**
   * Method encode
   *
   * @access public
   *
   * @param string $value original text cell
   *
   * @return string formula-safe, reversibly encoded cell
   */
  public function encode(string $value): string
  {
    if (str_starts_with($value, "'")
      || 1 === preg_match('/^[\x00-\x20\x7f]/', $value)
      || 1 === preg_match('/^[=+\-@]/', $value)
      || 1 === preg_match('/^[\p{Z}\p{C}]/u', $value)) {
      return "'" . $value;
    }

    return $value;
  }

  /**
   * Method decode
   *
   * @access public
   *
   * @param string $value a cell from an explicitly marked export row
   *
   * @return string the exact original cell
   */
  public function decode(string $value): string
  {
    return str_starts_with($value, "'") ? substr($value, 1) : $value;
  }
  // #endregion
}
