<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

use function sprintf;

/**
 * Exception MailSendingException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MailSendingException extends InfrastructureException
{
  // #region Factory Methods
  /**
   * Method dispatchFailed.
   *
   * @static
   *
   * Create an exception when dispatching
   * an email fails.
   *
   * @since 1.0.0
   *
   * @param string $subject the subject of the email that failed to send
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return self the created exception instance
   */
  public static function dispatchFailed(string $subject, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to send email with subject "%s".', $subject),
      previous: $previous,
    );
  }
  // #endregion
}
