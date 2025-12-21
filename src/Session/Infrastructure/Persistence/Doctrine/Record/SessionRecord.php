<?php

declare(strict_types=1);

namespace Session\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record SessionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
#[ORM\Index(name: 'idx_session_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_session_user_active', columns: ['user_id', 'revoked_at'])]
final class SessionRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The session ID.
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
   * Property accessTokenId.
   *
   * The current access token ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'access_token_id', type: 'string', length: 255, nullable: true)]
  public ?string $accessTokenId = null;

  /**
   * Property refreshTokenId.
   *
   * The current refresh token ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'refresh_token_id', type: 'string', length: 255, nullable: true)]
  public ?string $refreshTokenId = null;

  /**
   * Property ipAddress.
   *
   * The client IP address.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'ip_address', type: 'string', length: 45)]
  public string $ipAddress;

  /**
   * Property userAgent.
   *
   * The client user agent.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_agent', type: 'string', length: 512)]
  public string $userAgent;

  /**
   * Property metadata.
   *
   * Additional session metadata as JSON.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(type: 'json')]
  public array $metadata = [];

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property lastActivityAt.
   *
   * The last activity timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_activity_at', type: 'datetime_immutable')]
  public DateTimeImmutable $lastActivityAt;

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
