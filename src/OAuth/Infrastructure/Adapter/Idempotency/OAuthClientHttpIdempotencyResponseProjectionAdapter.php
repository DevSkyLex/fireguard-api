<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Command\Client\DeactivateClient\DeactivateClientCommand;
use OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\RegenerateClientSecretCommand;
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientQuery;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Captures the client reads used after mutation while the auth commit is still pending.
 *
 * @category Adapter
 */
final readonly class OAuthClientHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  public function supports(string $operation): bool
  {
    return in_array($operation, ['oauth_client_update', 'oauth_client_regenerate_secret', 'oauth_client_activate', 'oauth_client_deactivate'], true);
  }

  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($command instanceof UpdateClientDetailsCommand || $command instanceof RegenerateClientSecretCommand || $command instanceof ActivateClientCommand || $command instanceof DeactivateClientCommand) {
      return [new GetClientQuery($command->clientId)];
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }
}
