<?php

declare(strict_types=1);

namespace Import\Domain\Exception;

use RuntimeException;

/** Exception ImportConfirmationNotAllowedException. Confirmation never overrides simulation failures. */
final class ImportConfirmationNotAllowedException extends RuntimeException
{
  public static function unsuccessful(): self
  {
    return new self('Only a completed, non-empty simulation without failed rows can be confirmed.');
  }

  public static function missingFile(): self
  {
    return new self('The retained CSV is no longer available. Upload a new file and simulate it again.');
  }
}
