<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record FederatedIdentityRecord.
 *
 * Doctrine persistence shape for a provider identity linked to a user.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'federated_identities')]
#[ORM\UniqueConstraint(name: 'uniq_federated_provider_subject', columns: ['provider', 'subject'])]
#[ORM\UniqueConstraint(name: 'uniq_federated_user_provider', columns: ['user_id', 'provider'])]
class FederatedIdentityRecord
{
  // #region Properties
  /**
   * Property id
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property userId
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property provider
   */
  #[ORM\Column(type: 'string', length: 20)]
  public string $provider;

  /**
   * Property subject
   */
  #[ORM\Column(type: 'string', length: 255)]
  public string $subject;

  /**
   * Property email
   */
  #[ORM\Column(type: 'string', length: 320)]
  public string $email;

  /**
   * Property connectedAt
   */
  #[ORM\Column(name: 'connected_at', type: 'datetime_immutable')]
  public DateTimeImmutable $connectedAt;

  /**
   * Property lastUsedAt
   */
  #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable')]
  public DateTimeImmutable $lastUsedAt;
  // #endregion
}
