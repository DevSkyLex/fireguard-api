<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\RequestEmailChange;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class RequestEmailChangeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestEmailChangeResult implements ResultMessage
{
  // #region Constants
  /**
   * Error code returned when the current password is incorrect.
   */
  public const string ERROR_INVALID_PASSWORD = 'invalid_password';

  /**
   * Error code returned when the requested address cannot be used.
   *
   * Deliberately covers BOTH "already registered" and "identical to
   * the current address" so the response does not distinguish them.
   */
  public const string ERROR_EMAIL_NOT_AVAILABLE = 'email_not_available';

  /**
   * Error code returned when the authenticated user cannot be resolved
   * or cannot operate in its current state.
   */
  public const string ERROR_USER_NOT_FOUND = 'user_not_found';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the request was accepted
   * @param string|null $message optional message (for user display)
   * @param string|null $errorCode error code when failed
   * @param DateTimeImmutable|null $expiresAt confirmation token expiry (if accepted)
   */
  public function __construct(
    public bool $success,
    public ?string $message = null,
    public ?string $errorCode = null,
    public ?DateTimeImmutable $expiresAt = null,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result.
   *
   * @param DateTimeImmutable $expiresAt confirmation token expiry
   */
  public static function success(DateTimeImmutable $expiresAt): self
  {
    return new self(
      success: true,
      message: 'A confirmation link has been sent to the new email address.',
      expiresAt: $expiresAt,
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
