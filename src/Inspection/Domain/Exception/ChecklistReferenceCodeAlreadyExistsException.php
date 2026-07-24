<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ChecklistReferenceCodeAlreadyExistsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChecklistReferenceCodeAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withReferenceCode.
   *
   * Creates an exception for a duplicate checklist reference code within
   * the same organization.
   *
   * @since 1.0.0
   *
   * @param string $referenceCode the duplicate reference code
   *
   * @return self the exception instance
   */
  public static function withReferenceCode(string $referenceCode): self
  {
    return new self(sprintf('Checklist reference code "%s" already exists in this organization.', $referenceCode));
  }
  // #endregion
}
