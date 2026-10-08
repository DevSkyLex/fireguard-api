<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Interface SpreadsheetSafeTextPort
 *
 * Encodes spreadsheet text without evaluating formulas. Decoding is permitted
 * only for CSV rows carrying the published encoding marker; ordinary imports
 * retain their literal apostrophes.
 *
 * @category Port
 */
interface SpreadsheetSafeTextPort
{
  // #region Constants
  /**
   * Constant ENCODING_COLUMN
   *
   * Identifies the reversible encoding without changing existing data columns.
   */
  public const string ENCODING_COLUMN = '_fireguard_text_encoding';

  /**
   * Constant ENCODING_VERSION
   */
  public const string ENCODING_VERSION = 'apostrophe-v1';
  // #endregion

  // #region Methods
  /**
   * Method encode
   *
   * @access public
   *
   * @param string $value original text cell, including its whitespace
   *
   * @return string formula-safe text with reversible apostrophe protection
   */
  public function encode(string $value): string;

  /**
   * Method decode
   *
   * @access public
   *
   * @param string $value text from a row marked with ENCODING_VERSION
   *
   * @return string original text before spreadsheet protection
   */
  public function decode(string $value): string;
  // #endregion
}
