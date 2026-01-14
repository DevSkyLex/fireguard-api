<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

/**
 * Contract VerificationInfo.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VerificationInfo
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether verification succeeded
   * @param int $attemptsRemaining remaining attempts
   * @param string|null $error error message when verification fails
   */
  public function __construct(
    public bool $success,
    public int $attemptsRemaining = 0,
    public ?string $error = null,
  ) {
  }
  // #endregion
}
