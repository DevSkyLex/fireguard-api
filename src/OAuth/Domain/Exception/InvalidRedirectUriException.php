<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\DomainException;
use OAuth\Domain\ValueObject\RedirectUri;

use function sprintf;

/**
 * Exception InvalidRedirectUriException
 * @final
 *
 * Thrown when a redirect URI is not allowed for a client.
 *
 * @category Exception
 * @package OAuth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidRedirectUriException extends DomainException
{
  /**
   * Method forUri
   * @static
   *
   * Creates an exception for an invalid redirect URI.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RedirectUri $uri The invalid redirect URI.
   *
   * @return self The exception instance.
   */
  public static function forUri(RedirectUri $uri): self
  {
    return new self(
      message: sprintf('Redirect URI "%s" is not allowed for this client.', $uri->value)
    );
  }
}
