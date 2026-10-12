<?php

declare(strict_types=1);

namespace Session\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function is_string;

/**
 * Class SessionHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks fresh owner scope before any stored result is decoded; deleted targets require signed original ownership.
 *
 * @category Adapter
 */
final readonly class SessionHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current caller and signed permission checks
   * @param Connection $connection explicitly wired owner database, bypassing managed resource caches
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (SessionHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $facts = $this->authorizer->authorize($request, $storedProof, null);
    $target = null;
    if ('session_revoke' === $request->operation->name) {
      $target = $request->uriVariables['id'] ?? null;
      if (!is_string($target) || '' === $target) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $row = $this->connection->fetchAssociative('SELECT id, user_id FROM sessions WHERE id = ?', [$target]);
      if (false === $row) {
        if (null === $storedProof || ($storedProof->metadata['sessionOwnerId'] ?? null) !== $request->principalId || ($storedProof->metadata['targetId'] ?? null) !== $target) {
          throw new IdempotencyDeniedException('access_denied');
        }
      } elseif (($row['user_id'] ?? null) !== $request->principalId) {
        throw new IdempotencyDeniedException('access_denied');
      }
    }
    if (null !== $target) {
      $facts['targetId'] = $target;
      $facts['sessionOwnerId'] = $request->principalId;
    }

    return $facts;
  }
  // #endregion
}
