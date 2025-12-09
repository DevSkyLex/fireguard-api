<?php

declare(strict_types=1);

namespace Tenant\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Exception TenantNotFoundException
 * @final
 *
 * Thrown when a tenant cannot be found.
 *
 * @category Exception
 * @package Tenant\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantNotFoundException extends EntityNotFoundException
{
  //#region Methods
  /**
   * Method withId
   * @static
   *
   * Creates an exception for a missing tenant by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The tenant ID.
   *
   * @return self The exception.
   */
  public static function withId(string $id): self
  {
    return new self(
      message: sprintf('Tenant with ID "%s" not found.', $id)
    );
  }
  //#endregion
}
