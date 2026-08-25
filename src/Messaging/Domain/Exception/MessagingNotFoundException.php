<?php

declare(strict_types=1);

namespace Messaging\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MessagingNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingNotFoundException extends RuntimeException
{
  /**
   * Method conversation.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the conversation id value
   *
   * @return self the conversation not found result
   */
  public static function conversation(string $id): self
  {
    return new self(sprintf('Conversation with ID "%s" not found.', $id));
  }

  /**
   * Method message.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the message id value
   *
   * @return self the message not found result
   */
  public static function message(string $id): self
  {
    return new self(sprintf('Message with ID "%s" not found.', $id));
  }

  /**
   * Method outsideScope.
   *
   * The answer for a record owned by an organization the caller is not an
   * active member of. It has to be indistinguishable from a genuinely unknown
   * id: a 403 here would confirm the record exists, which is all an attacker
   * needs to enumerate another tenant's conversations by id.
   *
   * @static
   *
   * @since 1.1.0
   *
   * @return self the not found result
   */
  public static function outsideScope(): self
  {
    return new self('Not found.');
  }
}
