<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpResult};
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeResult};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares generic challenge mutations and their explicit auth commit owner.
 *
 * @category Adapter
 */
final readonly class OtpChallengeHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation('createChallenge', 'auth', 'bus_owner', GenerateOtpCommand::class, GenerateOtpResult::class, statusCode: 201, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('resendChallenge', 'auth', 'bus_owner', ResendChallengeCommand::class, ResendChallengeResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('verifyChallenge', 'auth', 'bus_owner', VerifyOtpCommand::class, VerifyOtpResult::class, secret: true, requiredKey: true),
    ];
  }
}
