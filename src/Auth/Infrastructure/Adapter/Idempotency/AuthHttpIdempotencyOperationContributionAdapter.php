<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\Federation\{FederatedConnection, FederatedConnections, FederatedLogin, ProviderId};
use Auth\Application\Contract\Token\AuthenticationSession;
use Auth\Application\UseCase\Command\Federation\CompleteFederation\{CompleteFederationCommand, CompleteFederationResult};
use Auth\Application\UseCase\Command\Federation\DisconnectFederation\{DisconnectFederationCommand, DisconnectFederationResult};
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyCommand, MfaVerifyResult};
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginResult};
use Auth\Application\UseCase\Command\Session\Logout\{LogoutCommand, LogoutResult};
use Auth\Application\UseCase\Command\Session\RefreshToken\{RefreshTokenCommand, RefreshTokenResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares credential-sensitive owner transactions and encrypted application results.
 *
 * @category Adapter
 */
final readonly class AuthHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    $federation = [FederatedLogin::class, AuthenticationSession::class, FederatedConnections::class, FederatedConnection::class, ProviderId::class];

    return [
      new HttpIdempotencyOperation('login', 'auth', 'bus_owner', LoginCommand::class, LoginResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('refresh', 'auth', 'bus_owner', RefreshTokenCommand::class, RefreshTokenResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('mfa_verify', 'auth', 'bus_owner', MfaVerifyCommand::class, MfaVerifyResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('logout', 'auth', 'bus_owner', LogoutCommand::class, LogoutResult::class, requiredKey: true),
      new HttpIdempotencyOperation('federated_disconnect', 'auth', 'root_owner', DisconnectFederationCommand::class, DisconnectFederationResult::class, $federation, requiredKey: true),
      new HttpIdempotencyOperation('federated_login_complete', 'auth', 'root_owner', CompleteFederationCommand::class, CompleteFederationResult::class, $federation, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('federated_link_complete', 'auth', 'root_owner', CompleteFederationCommand::class, CompleteFederationResult::class, $federation, secret: true, requiredKey: true),
    ];
  }
}
