<?php

declare(strict_types=1);

namespace Import\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ImportJobNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the import job id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Import job with ID "%s" not found.', $id));
  }
  // #endregion
}
