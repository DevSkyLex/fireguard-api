<?php

declare(strict_types=1);

namespace Import\Domain\Exception;

use RuntimeException;

/** Exception ImportConfirmationNotAllowedException. Confirmation never overrides simulation failures. */
final class ImportConfirmationNotAllowedException extends RuntimeException
{
  /**
   * Method unsuccessful.
   *
   * Creates the exception for a simulation that cannot be confirmed.
   *
   * @access public
   *
   * @static
   *
   * @return self the confirmation refusal exception
   */
  public static function unsuccessful(): self
  {
    return new self('Only a completed, non-empty simulation without failed rows can be confirmed.');
  }

  /**
   * Method missingFile.
   *
   * Creates the exception for a retained CSV file that is no longer available.
   *
   * @access public
   *
   * @static
   *
   * @return self the missing file exception
   */
  public static function missingFile(): self
  {
    return new self('The retained CSV is no longer available. Upload a new file and simulate it again.');
  }
}
