<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\UseCase\Command\Token\RevokeToken\{RevokeTokenCommand, RevokeTokenResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares native revocation's empty public outcome on the auth owning commit.
 *
 * @category Adapter
 */
final readonly class OAuthRevocationHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('revoke_token', 'auth', 'bus_owner', RevokeTokenCommand::class, RevokeTokenResult::class, requiredKey: true)];
  }
}
