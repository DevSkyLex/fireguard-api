<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record CalendarFeedTokenRecord.
 *
 * Member-scoped iCal feed subscription tokens (main database). Stores only
 * the SHA-256 hash of the secret — the raw secret is never persisted.
 * `organization_id` and `user_id` are plain columns (no ORM association, no
 * foreign key), mirroring {@see CalendarEventRecord}: Calendar never depends
 * on the Organization or User ORM mappings — the User record lives on the
 * auth database anyway, and no key may cross that line.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'member_calendar_feed_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_member_calendar_feed_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_member_calendar_feed_token_org_user', columns: ['organization_id', 'user_id'])]
class CalendarFeedTokenRecord
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
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property tokenHash.
   *
   * SHA-256 of the raw secret, hex encoded (64 characters).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
  public string $tokenHash;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property lastUsedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastUsedAt = null;

  /**
   * Property revokedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $revokedAt = null;
  // #endregion
}
