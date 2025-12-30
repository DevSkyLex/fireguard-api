<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception\Client;

use OAuth\Domain\ValueObject\Client\RedirectUri;
use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception InvalidRedirectUriException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidRedirectUriException extends DomainException
{
  /**
   * Method forUri.
   *
   * @static
   *
   * Creates an exception for an invalid redirect URI.
   *
   * @since 1.0.0
   *
   * @param RedirectUri $uri the invalid redirect URI
   *
   * @return self the exception instance
   */
  public static function forUri(RedirectUri $uri): self
  {
    return new self(
      message: sprintf('Redirect URI "%s" is not allowed for this client.', $uri->value),
    );
  }
}
