<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use OAuth\Domain\ValueObject\ClientId;
use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception InvalidClientException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidClientException extends DomainException
{
  /**
   * Method forId.
   *
   * @static
   *
   * Creates an exception for an invalid client ID.
   *
   * @since 1.0.0
   *
   * @param ClientId $clientId the invalid client ID
   *
   * @return self the exception instance
   */
  public static function forId(ClientId $clientId): self
  {
    return new self(
      message: sprintf('Client with ID "%s" is invalid or not found.', $clientId->value)
    );
  }

  /**
   * Method inactive.
   *
   * @static
   *
   * Creates an exception for an inactive client.
   *
   * @since 1.0.0
   *
   * @param ClientId $clientId the client ID
   *
   * @return self the exception instance
   */
  public static function inactive(ClientId $clientId): self
  {
    return new self(
      message: sprintf('Client "%s" is inactive.', $clientId->value)
    );
  }
}
