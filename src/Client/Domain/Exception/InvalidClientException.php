<?php

declare(strict_types=1);

namespace Client\Domain\Exception;

use Shared\Domain\Exception\DomainException;
use Client\Domain\ValueObject\ClientId;

use function sprintf;

/**
 * Exception InvalidClientException
 * @final
 *
 * Thrown when an OAuth client is invalid or not found.
 *
 * @category Exception
 * @package Client\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidClientException extends DomainException
{
  /**
   * Method forId
   * @static
   *
   * Creates an exception for an invalid client ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientId $clientId The invalid client ID.
   *
   * @return self The exception instance.
   */
  public static function forId(ClientId $clientId): self
  {
    return new self(
      message: sprintf('Client with ID "%s" is invalid or not found.', $clientId->value)
    );
  }

  /**
   * Method inactive
   * @static
   *
   * Creates an exception for an inactive client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientId $clientId The client ID.
   *
   * @return self The exception instance.
   */
  public static function inactive(ClientId $clientId): self
  {
    return new self(
      message: sprintf('Client "%s" is inactive.', $clientId->value)
    );
  }
}
