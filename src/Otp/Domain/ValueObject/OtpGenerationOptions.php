<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/** Optional policy overrides for a newly generated challenge. */
final readonly class OtpGenerationOptions
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Provides optional overrides for OTP challenge generation policy.
   *
   * @access public
   *
   * @param ?int $ttlSeconds optional challenge lifetime in seconds
   * @param ?int $maxAttempts optional maximum number of verification attempts
   * @param ?int $codeLength optional number of digits in the generated code
   *
   * @return void
   */
  public function __construct(
    public ?int $ttlSeconds = null,
    public ?int $maxAttempts = null,
    public ?int $codeLength = null,
  ) {
  }
  // #endregion
}
