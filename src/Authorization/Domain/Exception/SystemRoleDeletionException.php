<?php

declare(strict_types=1);

namespace Authorization\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception SystemRoleDeletionException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SystemRoleDeletionException extends DomainException
{
  // #region Methods
  /**
   * Method forRole.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $roleId the role ID
   *
   * @return self the exception
   */
  public static function forRole(string $roleId): self
  {
    return new self(
      message: sprintf(
        'System role "%s" cannot be deleted.',
        $roleId,
      ),
    );
  }
  // #endregion
}
