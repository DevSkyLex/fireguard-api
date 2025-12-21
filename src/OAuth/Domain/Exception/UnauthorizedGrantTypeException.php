<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use OAuth\Domain\ValueObject\GrantType;
use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception UnauthorizedGrantTypeException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UnauthorizedGrantTypeException extends DomainException
{
  // #region Methods
  /**
   * Method forGrantType.
   *
   * @static
   *
   * Creates an exception for an unauthorized grant type.
   *
   * @since 1.0.0
   *
   * @param GrantType $grantType the unauthorized grant type
   *
   * @return self the exception instance
   */
  public static function forGrantType(GrantType $grantType): self
  {
    return new self(
      message: sprintf('Grant type "%s" is not authorized for this client.', $grantType->value),
    );
  }
  // #endregion
}
