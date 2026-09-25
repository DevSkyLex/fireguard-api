<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/** Optional policy overrides for a newly generated challenge. */
final readonly class OtpGenerationOptions
{
  public function __construct(
    public ?int $ttlSeconds = null,
    public ?int $maxAttempts = null,
    public ?int $codeLength = null,
  ) {
  }
}
