<?php

declare(strict_types=1);

namespace Session\Infrastructure\Adapter\Idempotency;

use Doctrine\ORM\EntityManagerInterface;
use Session\Application\Port\Inbound\SessionReplayProofPort;

use function is_string;

/**
 * Class SessionReplayProofAdapter
 *
 * Bypasses managed session state for an exact current, nonrevoked token pair owned by the verified principal.
 *
 * @category Adapter
 */
final readonly class SessionReplayProofAdapter implements SessionReplayProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly auth-owned session storage
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function currentSessionId(string $userId, string $accessTokenId, string $refreshTokenId): ?string
  {
    if ('' === $userId || '' === $accessTokenId || '' === $refreshTokenId) {
      return null;
    }
    $id = $this->entityManager->getConnection()->fetchOne(
      'SELECT id FROM sessions WHERE user_id = ? AND access_token_id = ? AND refresh_token_id = ? AND revoked_at IS NULL',
      [$userId, $accessTokenId, $refreshTokenId],
    );

    return is_string($id) ? $id : null;
  }
  // #endregion
}
