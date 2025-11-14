<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;

use function sprintf;
use Throwable;

/**
 * Exception MailSendingException
 * @final
 *
 * Exception thrown when a mail dispatch fails.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MailSendingException extends InfrastructureException
{
  //#region Factory Methods
  /**
   * Method dispatchFailed
   * @static
   *
   * Create an exception when dispatching
   * an email fails.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $subject The subject of the email that failed to send.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function dispatchFailed(string $subject, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to send email with subject "%s".', $subject),
      previous: $previous
    );
  }
  //#endregion
}
