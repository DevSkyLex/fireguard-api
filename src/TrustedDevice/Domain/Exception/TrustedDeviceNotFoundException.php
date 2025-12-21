<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception TrustedDeviceNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrustedDeviceNotFoundException extends EntityNotFoundException
{
  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new TrustedDeviceNotFoundException instance.
   *
   * @since 1.0.0
   *
   * @param string $id the ID of the trusted device that was not found
   *
   * @return self the created TrustedDeviceNotFoundException instance
   */
  public static function create(string $id): self
  {
    return new self(
      message: sprintf('TrustedDevice with ID "%s" not found.', $id),
    );
  }
  // #endregion
}
