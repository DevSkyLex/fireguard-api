<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record ConsentRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'consents')]
#[ORM\UniqueConstraint(name: 'uniq_user_client', columns: ['user_id', 'client_id'])]
#[ORM\Index(name: 'idx_consent_user', columns: ['user_id'])]
final class ConsentRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The consent ID.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: UuidType::NAME, unique: true)]
  public Uuid $id;

  /**
   * Property userId.
   *
   * The user ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property clientId.
   *
   * The client ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'client_id', type: 'string', length: 36)]
  public string $clientId;

  /**
   * Property scopes.
   *
   * The granted scopes.
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  #[ORM\Column(type: 'json')]
  public array $scopes = [];

  /**
   * Property grantedAt.
   *
   * The grant timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'granted_at', type: 'datetime_immutable')]
  public DateTimeImmutable $grantedAt;

  /**
   * Property revokedAt.
   *
   * The revocation timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $revokedAt = null;
  // #endregion
}
