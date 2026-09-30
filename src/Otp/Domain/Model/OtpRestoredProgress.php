<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use DateTimeImmutable;

/** Persisted verifier and attempt timeline of an OTP challenge. */
final readonly class OtpRestoredProgress
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted verification progress without exposing the original code.
   *
   * @access public
   *
   * @param string $codeHash stored hash used to verify submitted codes
   * @param DateTimeImmutable $expiresAt time after which the challenge expires
   * @param int $maxAttempts maximum number of verification attempts
   * @param int $attempts number of attempts already made
   * @param ?DateTimeImmutable $verifiedAt optional time when the challenge was verified
   * @param DateTimeImmutable $createdAt challenge creation timestamp
   *
   * @return void
   */
  public function __construct(
    public string $codeHash,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
    public int $attempts,
    public ?DateTimeImmutable $verifiedAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
