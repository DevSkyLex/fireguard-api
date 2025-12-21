<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AuthCodeRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'auth_codes')]
final class AuthCodeRecord
{
  // #region Properties
  /**
   * Property identifier.
   *
   * The token identifier.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 100, unique: true)]
  public string $identifier;

  /**
   * Property clientIdentifier.
   *
   * The client identifier.
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $clientIdentifier;

  /**
   * Property userIdentifier.
   *
   * The user identifier (nullable).
   */
  #[ORM\Column(type: 'string', length: 100, nullable: true)]
  public ?string $userIdentifier = null;

  /**
   * Property scopes.
   *
   * The scopes associated with the code.
   *
   * @var array<string>
   */
  #[ORM\Column(type: 'json')]
  public array $scopes = [];

  /**
   * Property redirectUri.
   *
   * The redirect URI (nullable).
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $redirectUri = null;

  /**
   * Property expiry.
   *
   * The expiry date and time.
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $expiry;

  /**
   * Property isRevoked.
   *
   * Whether the code is revoked.
   */
  #[ORM\Column(type: 'boolean')]
  public bool $isRevoked = false;
  // #endregion
}
