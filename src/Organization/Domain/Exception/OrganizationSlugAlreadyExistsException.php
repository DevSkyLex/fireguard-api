<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationSlugAlreadyExistsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSlugAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withSlug.
   *
   * Creates an exception for a duplicate organization slug.
   *
   * @since 1.0.0
   *
   * @param string $slug the duplicate organization slug
   *
   * @return self the exception instance
   */
  public static function withSlug(string $slug): self
  {
    return new self(sprintf('Organization slug "%s" already exists.', $slug));
  }
  // #endregion
}
