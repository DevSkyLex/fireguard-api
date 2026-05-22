<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AuditEventChainRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_event_chains')]
class AuditEventChainRecord
{
  // #region Properties
  /**
   * Property chainId.
   *
   * The audit chain identifier.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'chain_id', type: 'string', length: 128)]
  public string $chainId;

  /**
   * Property lastHash.
   *
   * The last hash in the chain.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_hash', type: 'string', length: 64)]
  public string $lastHash;

  /**
   * Property lastSequence.
   *
   * The last sequence number in the chain.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_sequence', type: 'bigint')]
  public int $lastSequence;

  /**
   * Property updatedAt.
   *
   * The last update timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
