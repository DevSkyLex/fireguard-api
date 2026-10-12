<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResultProofContributionPort;
use User\Application\UseCase\Command\User\CreateUser\CreateUserResult;

/**
 * Class UserHttpIdempotencyResultProofAdapter.
 *
 * Publishes the server-created account association only after its owner row is flushed in the authorized transaction.
 *
 * @category Adapter
 */
final readonly class UserHttpIdempotencyResultProofAdapter implements HttpIdempotencyResultProofContributionPort
{
  // #region Constructor
  /**
   * @param Connection $connection the explicit auth write connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(HttpIdempotencyOperation $operation): bool
  {
    return 'user_create' === $operation->name;
  }

  /**
   * {@inheritdoc}
   */
  public function proofForResult(HttpIdempotencyOperation $operation, ResultMessage $result, array $signedInitialFacts): array
  {
    if (!$this->supports($operation) || 'auth' !== $operation->database || !$result instanceof CreateUserResult || ($signedInitialFacts['resourceId'] ?? null) !== $result->userId || !isset($signedInitialFacts['principalId'], $signedInitialFacts['tenantId'])) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $row = $this->connection->fetchAssociative('SELECT id, tenant_id FROM users WHERE id = ?', [$result->userId]);
    if (false === $row || ($row['id'] ?? null) !== $result->userId || ($row['tenant_id'] ?? '') !== $signedInitialFacts['tenantId']) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return ['user_id' => $result->userId, 'targetUserId' => $result->userId];
  }
  // #endregion
}
