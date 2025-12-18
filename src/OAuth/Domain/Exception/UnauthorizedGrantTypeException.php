<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\DomainException;
use OAuth\Domain\ValueObject\GrantType;

use function sprintf;

/**
 * Exception UnauthorizedGrantTypeException
 * @final
 *
 * Thrown when a client attempts to use an unauthorized grant type.
 *
 * @category Exception
 * @package OAuth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UnauthorizedGrantTypeException extends DomainException
{
  //#region Methods
  /**
   * Method forGrantType
   * @static
   *
   * Creates an exception for an unauthorized grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantType $grantType The unauthorized grant type.
   *
   * @return self The exception instance.
   */
  public static function forGrantType(GrantType $grantType): self
  {
    return new self(
      message: sprintf('Grant type "%s" is not authorized for this client.', $grantType->value)
    );
  }
  //#endregion
}
