<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Exception\ApplicationException;

use function sprintf;

/**
 * Exception ResendNotAllowedException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ResendNotAllowedException extends ApplicationException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param int $retryAfterSeconds seconds until resend is allowed
   */
  public function __construct(private readonly int $retryAfterSeconds)
  {
    parent::__construct(
      message: sprintf(
        'Resend not allowed. Retry after %d seconds.',
        $retryAfterSeconds,
      ),
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method retryAfterSeconds
   *
   * Returns the number of seconds before another resend is allowed.
   *
   * @access public
   *
   * @return int the remaining wait in seconds
   */
  public function retryAfterSeconds(): int
  {
    return $this->retryAfterSeconds;
  }

  /**
   * Method context
   *
   * Returns the retry delay in the exception context payload.
   *
   * @access public
   *
   * @return array{retryAfterSeconds: int} the exception context
   */
  public function context(): array
  {
    return [
      'retryAfterSeconds' => $this->retryAfterSeconds,
    ];
  }
  // #endregion
}
