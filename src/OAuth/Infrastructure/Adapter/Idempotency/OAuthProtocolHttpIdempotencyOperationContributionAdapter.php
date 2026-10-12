<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\UseCase\Command\Token\IssueToken\{IssueTokenCommand, IssueTokenResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares the OAuth protocol token write separately from resource CRUD.
 *
 * @category Adapter
 */
final readonly class OAuthProtocolHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('token', 'auth', 'bus_owner', IssueTokenCommand::class, IssueTokenResult::class, secret: true, requiredKey: true)];
  }
}
