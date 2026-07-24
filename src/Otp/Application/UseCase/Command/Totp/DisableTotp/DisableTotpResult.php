<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\DisableTotp;

use Shared\Application\Message\ResultMessage;

/**
 * Result DisableTotpResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisableTotpResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether TOTP was disabled
   * @param string|null $error error message if failed
   */
  public function __construct(
    public readonly bool $success,
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
   * @param string $error error message
   */
  public static function failed(string $error): self
  {
    return new self(success: false, error: $error);
  }
  // #endregion
}
