<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\ConfirmEmailChange;

use Shared\Application\Message\ResultMessage;

/**
 * Class ConfirmEmailChangeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailChangeResult implements ResultMessage
{
  // #region Constants
  /**
   * Error code returned for an unknown, expired or already-used token.
   * One code for all three on purpose: the response must not reveal
   * which check failed.
   */
  public const string ERROR_INVALID_TOKEN = 'invalid_token';

  /**
   * Error code returned when the target address was registered by
   * someone else between the request and the confirmation.
   */
  public const string ERROR_EMAIL_NOT_AVAILABLE = 'email_not_available';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the change was applied
   * @param string|null $message optional message (for user display)
   * @param string|null $errorCode error code when failed
   */
  public function __construct(
    public bool $success,
    public ?string $message = null,
    public ?string $errorCode = null,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result.
   */
  public static function success(): self
  {
    return new self(
      success: true,
      message: 'Your email address has been changed. Please sign in again with the new address.',
    );
  }

  /**
   * Creates a failed result.
   *
   * @param string $message error message
   * @param string $errorCode error code
   */
  public static function failed(string $message, string $errorCode): self
  {
    return new self(
      success: false,
      message: $message,
      errorCode: $errorCode,
    );
  }
  // #endregion
}
