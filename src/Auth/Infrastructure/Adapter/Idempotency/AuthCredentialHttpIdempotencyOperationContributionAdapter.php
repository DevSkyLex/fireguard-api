<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\{ConfirmEmailOwnershipCommand, ConfirmEmailOwnershipResult};
use Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership\{StartEmailOwnershipCommand, StartEmailOwnershipResult};
use Auth\Application\UseCase\Command\Mfa\MfaResend\{MfaResendCommand, MfaResendResult};
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\{ConfirmPasswordChangeCommand, ConfirmPasswordChangeResult};
use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\{RequestPasswordChangeCommand, RequestPasswordChangeResult};
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{ConfirmPasswordResetCommand, ConfirmPasswordResetResult};
use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\{RequestPasswordResetCommand, RequestPasswordResetResult};
use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\{ResendPasswordResetCommand, ResendPasswordResetResult};
use Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup\{ConfirmPasswordSetupCommand, ConfirmPasswordSetupResult};
use Auth\Application\UseCase\Command\PasswordSetup\RequestPasswordSetup\{RequestPasswordSetupCommand, RequestPasswordSetupResult};
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{ConfirmRegistrationCommand, ConfirmRegistrationResult};
use Auth\Application\UseCase\Command\Registration\RegisterUser\{RegisterUserCommand, RegisterUserResult};
use Auth\Application\UseCase\Command\Registration\ResendRegistration\{ResendRegistrationCommand, ResendRegistrationResult};
use Otp\Application\Contract\Challenge\ChallengeInfo;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares credential and mailbox mutations whose owner commits the receipt with its proof consumption.
 *
 * @category Adapter
 */
final readonly class AuthCredentialHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation('register_user', 'auth', 'bus_owner', RegisterUserCommand::class, RegisterUserResult::class, [], statusCode: 201, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('resend_registration', 'auth', 'bus_owner', ResendRegistrationCommand::class, ResendRegistrationResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('confirm_registration', 'auth', 'bus_owner', ConfirmRegistrationCommand::class, ConfirmRegistrationResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('request_password_reset', 'auth', 'bus_owner', RequestPasswordResetCommand::class, RequestPasswordResetResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('resend_password_reset', 'auth', 'bus_owner', ResendPasswordResetCommand::class, ResendPasswordResetResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('confirm_password_reset', 'auth', 'bus_owner', ConfirmPasswordResetCommand::class, ConfirmPasswordResetResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('request_password_change', 'auth', 'bus_owner', RequestPasswordChangeCommand::class, RequestPasswordChangeResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('confirm_password_change', 'auth', 'bus_owner', ConfirmPasswordChangeCommand::class, ConfirmPasswordChangeResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('request_initial_password', 'auth', 'bus_owner', RequestPasswordSetupCommand::class, RequestPasswordSetupResult::class, [ChallengeInfo::class], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('confirm_initial_password', 'auth', 'bus_owner', ConfirmPasswordSetupCommand::class, ConfirmPasswordSetupResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('auth_email_ownership_start', 'auth', 'bus_owner', StartEmailOwnershipCommand::class, StartEmailOwnershipResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('auth_email_ownership_confirm', 'auth', 'bus_owner', ConfirmEmailOwnershipCommand::class, ConfirmEmailOwnershipResult::class, [], statusCode: 200, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('mfa_resend', 'auth', 'bus_owner', MfaResendCommand::class, MfaResendResult::class, [], statusCode: 200, secret: true, requiredKey: true),
    ];
  }
}
