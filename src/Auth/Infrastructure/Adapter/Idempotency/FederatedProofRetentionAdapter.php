<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyRetentionPort;

/**
 * Class FederatedProofRetentionAdapter
 *
 * Physically erases expired encrypted provider proof on auth while retaining safe flow-stage tombstones.
 *
 * @category Adapter
 */
final readonly class FederatedProofRetentionAdapter implements IdempotencyRetentionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly auth-owned flow storage
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
  public function eraseExpiredResults(DateTimeImmutable $now): int
  {
    return (int) $this->entityManager->getConnection()->executeStatement(
      'UPDATE federated_auth_flows SET verified_profile = NULL WHERE proof_expires_at <= :now AND verified_profile IS NOT NULL',
      ['now' => $now],
      ['now' => Types::DATETIMETZ_IMMUTABLE],
    );
  }
  // #endregion
}
