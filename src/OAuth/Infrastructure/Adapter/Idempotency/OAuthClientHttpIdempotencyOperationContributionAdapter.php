<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Command\Client\DeactivateClient\DeactivateClientCommand;
use OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand;
use OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\{RegenerateClientSecretCommand, RegenerateClientSecretResult};
use OAuth\Application\UseCase\Command\Client\RegisterClient\{RegisterClientCommand, RegisterClientResult};
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares every client mutation on auth with a bounded secret result and exact output read schemas.
 *
 * @category Adapter
 */
final readonly class OAuthClientHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation('oauth_client_create', 'auth', 'bus_owner', RegisterClientCommand::class, RegisterClientResult::class, statusCode: 201, secret: true, requiredKey: true),
      new HttpIdempotencyOperation('oauth_client_update', 'auth', 'bus_owner', UpdateClientDetailsCommand::class, VoidResult::class, statusCode: 200, secret: false, requiredKey: true, queryProjections: [new HttpIdempotencyQueryProjection(GetClientQuery::class, GetClientResult::class)]),
      new HttpIdempotencyOperation('oauth_client_delete', 'auth', 'bus_owner', DeleteClientCommand::class, VoidResult::class, statusCode: 204, secret: false, requiredKey: true),
      new HttpIdempotencyOperation('oauth_client_regenerate_secret', 'auth', 'bus_owner', RegenerateClientSecretCommand::class, RegenerateClientSecretResult::class, statusCode: 200, secret: true, requiredKey: true, queryProjections: [new HttpIdempotencyQueryProjection(GetClientQuery::class, GetClientResult::class)]),
      new HttpIdempotencyOperation('oauth_client_activate', 'auth', 'bus_owner', ActivateClientCommand::class, VoidResult::class, statusCode: 200, secret: false, requiredKey: true, queryProjections: [new HttpIdempotencyQueryProjection(GetClientQuery::class, GetClientResult::class)]),
      new HttpIdempotencyOperation('oauth_client_deactivate', 'auth', 'bus_owner', DeactivateClientCommand::class, VoidResult::class, statusCode: 200, secret: false, requiredKey: true, queryProjections: [new HttpIdempotencyQueryProjection(GetClientQuery::class, GetClientResult::class)]),
    ];
  }
}
