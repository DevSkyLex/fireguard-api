<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record UserRecord.
 *
 * Doctrine entity for user persistence.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_username', columns: ['username'])]
#[ORM\Index(name: 'idx_email', columns: ['email'])]
#[ORM\Index(name: 'idx_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
class UserRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The user ID.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property username.
   *
   * The username.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 50, unique: true)]
  public string $username;

  /**
   * Property email.
   *
   * The user email.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255, unique: true)]
  public string $email;

  /**
   * Property password.
   *
   * The hashed password.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  public ?string $password = null;

  /**
   * Property securityRevision
   *
   * Durable credential/security epoch; login activity and ordinary profile changes leave it unchanged.
   */
  #[ORM\Column(name: 'security_revision', type: 'integer', options: ['default' => 1])]
  public int $securityRevision = 1;

  /**
   * Property firstName.
   *
   * The first name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $firstName;

  /**
   * Property lastName.
   *
   * The last name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $lastName;

  /**
   * Property avatarUrl.
   *
   * The avatar URL.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  public ?string $avatarUrl = null;

  /**
   * Immutable avatar generation; null reads historical canonical variants.
   */
  #[ORM\Column(name: 'avatar_storage_key', type: 'string', length: 36, nullable: true)]
  public ?string $avatarStorageKey = null;

  /**
   * Property status.
   *
   * The user status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 30)]
  public string $status;

  /**
   * Property emailVerified.
   *
   * Whether the email is verified.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'boolean')]
  public bool $emailVerified = false;

  /**
   * Explicit Fireguard proof of possession of the current email; legacy and OAuth accounts start unknown.
   *
   * @since 1.1.0
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $emailOwnershipVerifiedAt = null;

  /**
   * Property tenantId.
   *
   * The tenant ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $tenantId = null;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property lastLoginAt.
   *
   * The last login timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastLoginAt = null;

  /**
   * Property lastSignInMethod.
   *
   * The primary method used for the last completed sign-in.
   *
   * @since 1.1.0
   */
  #[ORM\Column(type: 'string', length: 20, nullable: true)]
  public ?string $lastSignInMethod = null;

  /**
   * Property failedLoginAttempts.
   *
   * The number of failed login attempts.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $failedLoginAttempts = 0;

  /**
   * Property locale.
   *
   * The preferred display language ("system" follows the browser).
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10, options: ['default' => 'system'])]
  public string $locale = 'system';

  // #endregion
}
