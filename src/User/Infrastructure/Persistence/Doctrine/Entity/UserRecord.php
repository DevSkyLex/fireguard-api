<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity UserRecord
 *
 * Doctrine entity for user persistence.
 *
 * @category Entity
 * @package User\Infrastructure\Persistence\Doctrine\Entity
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
  //#region Properties
  /**
   * Property id
   *
   * The user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property username
   *
   * The username.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 50, unique: true)]
  public string $username;

  /**
   * Property email
   *
   * The user email.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 255, unique: true)]
  public string $email;

  /**
   * Property password
   *
   * The hashed password.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 255)]
  public string $password;

  /**
   * Property firstName
   *
   * The first name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $firstName;

  /**
   * Property lastName
   *
   * The last name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $lastName;

  /**
   * Property avatarUrl
   *
   * The avatar URL.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  public ?string $avatarUrl = null;

  /**
   * Property status
   *
   * The user status.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 30)]
  public string $status;

  /**
   * Property emailVerified
   *
   * Whether the email is verified.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[ORM\Column(type: 'boolean')]
  public bool $emailVerified = false;

  /**
   * Property tenantId
   *
   * The tenant ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $tenantId = null;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property lastLoginAt
   *
   * The last login timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable|null
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastLoginAt = null;

  /**
   * Property failedLoginAttempts
   *
   * The number of failed login attempts.
   *
   * @access public
   * @since 1.0.0
   *
   * @var int
   */
  #[ORM\Column(type: 'integer')]
  public int $failedLoginAttempts = 0;
  //#endregion
}
