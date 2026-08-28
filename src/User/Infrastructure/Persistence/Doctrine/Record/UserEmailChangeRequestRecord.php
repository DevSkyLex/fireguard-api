<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record UserEmailChangeRequestRecord.
 *
 * Auth database. Stores pending (and confirmed, for the audit trail)
 * sign-in email change requests. The confirmation token is never
 * stored — only its SHA-256 hash.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_email_change_requests')]
#[ORM\Index(name: 'idx_user_email_change_request_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email_change_request_token_hash', columns: ['token_hash'])]
class UserEmailChangeRequestRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property currentEmail.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'current_email', type: 'string', length: 320)]
  public string $currentEmail;

  /**
   * Property newEmail.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'new_email', type: 'string', length: 320)]
  public string $newEmail;

  /**
   * Property tokenHash.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
  public string $tokenHash;

  /**
   * Property requestedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
  public DateTimeImmutable $requestedAt;

  /**
   * Property expiresAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  /**
   * Property confirmedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $confirmedAt = null;
  // #endregion
}
