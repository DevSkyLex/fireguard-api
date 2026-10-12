<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpResult};
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpResult};
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\{GenerateRecoveryCodesCommand, GenerateRecoveryCodesResult};
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares credential and mailbox mutations whose owner commits the receipt with its proof consumption.
 *
 * @category Adapter
 */
final readonly class OtpAuthenticatorHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation('setupTotp', 'auth', 'root_owner', SetupTotpCommand::class, SetupTotpResult::class, statusCode: 201, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('confirmTotp', 'auth', 'root_owner', ConfirmTotpCommand::class, ConfirmTotpResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('disableTotp', 'auth', 'root_owner', DisableTotpCommand::class, DisableTotpResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('generateRecoveryCodes', 'auth', 'root_owner', GenerateRecoveryCodesCommand::class, GenerateRecoveryCodesResult::class, secret: true, requiredKey: true),
    ];
  }
}
