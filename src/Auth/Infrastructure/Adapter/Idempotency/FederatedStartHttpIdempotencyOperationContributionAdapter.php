<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\UseCase\Command\Federation\StartFederation\{StartFederationCommand, StartFederationResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class FederatedStartHttpIdempotencyOperationContributionAdapter
 *
 * Declares authorization start as an encrypted Auth mutation with an explicit client key and browser-native proof.
 *
 * @category Adapter
 */
final readonly class FederatedStartHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation('federated_login_start', 'auth', 'bus_owner', StartFederationCommand::class, StartFederationResult::class, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('federated_link_start', 'auth', 'bus_owner', StartFederationCommand::class, StartFederationResult::class, secret: true, requiredKey: true),
    ];
  }
}
