<?php

declare(strict_types=1);

namespace Session\Infrastructure\Adapter\Idempotency;

use Session\Application\UseCase\Command\Session\RevokeAllUserSessions\{RevokeAllUserSessionsCommand, RevokeAllUserSessionsResult};
use Session\Application\UseCase\Command\Session\RevokeOtherUserSessions\{RevokeOtherUserSessionsCommand, RevokeOtherUserSessionsResult};
use Session\Application\UseCase\Command\Session\RevokeSession\{RevokeSessionCommand, RevokeSessionResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class SessionHttpIdempotencyOperationContributionAdapter
 *
 * Declares explicit owner transactions, result schemas and mandatory keys for every published HTTP mutation.
 *
 * @category Adapter
 */
final readonly class SessionHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'session_revoke',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RevokeSessionCommand::class,
        resultClass: RevokeSessionResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'sessionId',
        resourceKind: 'session',
      ),
      new HttpIdempotencyOperation(
        name: 'session_revoke_all',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RevokeAllUserSessionsCommand::class,
        resultClass: RevokeAllUserSessionsResult::class,
        statusCode: 201,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'session_revoke_others',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RevokeOtherUserSessionsCommand::class,
        resultClass: RevokeOtherUserSessionsResult::class,
        requiredKey: true,
      ),
    ];
  }
  // #endregion
}
