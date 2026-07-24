<?php

declare(strict_types=1);

namespace Messaging\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MessagingClientMessageAlreadyExistsException.
 *
 * Raised when a client-minted message id has already been used.
 *
 * This is what makes an offline outbox safe to replay: a queued send whose
 * response was lost can be retried without creating a second message, because
 * the second attempt conflicts on the id the client chose rather than minting a
 * new one. Callers should treat it as success — the message is already there.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingClientMessageAlreadyExistsException extends RuntimeException
{
  /**
   * Method forClientId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $clientId the client-minted message id value
   *
   * @return self the exception
   */
  public static function forClientId(string $clientId): self
  {
    return new self(sprintf('A message with the client identifier "%s" already exists.', $clientId));
  }
}
