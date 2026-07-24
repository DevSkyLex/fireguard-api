<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Shared\Application\Message\ResultMessage;

/**
 * Result ConfirmTotpResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmTotpResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether confirmation succeeded
   * @param int $attemptsRemaining remaining confirmation attempts
   * @param string|null $error error message if failed
   */
  public function __construct(
    public readonly bool $success,
    public readonly int $attemptsRemaining = 0,
    public readonly ?string $error = null,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method success.
   *
   * @static
   *
   * Creates a successful result.
   *
   * @since 1.0.0
   */
  public static function success(): self
  {
    return new self(success: true);
  }

  /**
   * Method failed.
   *
   * @static
   *
   * Creates a failed result.
   *
   * @since 1.0.0
   *
   * @param int $attemptsRemaining remaining attempts
   * @param string $error error message
   */
  public static function failed(int $attemptsRemaining, string $error): self
  {
    return new self(
      success: false,
      attemptsRemaining: $attemptsRemaining,
      error: $error,
    );
  }
  // #endregion
}
