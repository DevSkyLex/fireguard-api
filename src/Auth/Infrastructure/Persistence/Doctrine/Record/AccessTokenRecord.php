<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AccessTokenRecord
 * @final
 *
 * Doctrine entity for Access Token persistence.
 *
 * @category Record
 * @package Auth\Infrastructure\Persistence\Doctrine\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'access_tokens')]
final class AccessTokenRecord
{
  //#region Properties
  /**
   * Property identifier
   *
   * The token identifier.
   *
   * @var string
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 100, unique: true)]
  public string $identifier;

  /**
   * Property clientIdentifier
   *
   * The client identifier.
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $clientIdentifier;

  /**
   * Property userIdentifier
   *
   * The user identifier (nullable).
   *
   * @var string|null
   */
  #[ORM\Column(type: 'string', length: 100, nullable: true)]
  public ?string $userIdentifier = null;

  /**
   * Property scopes
   *
   * The scopes associated with the token.
   *
   * @var array<string>
   */
  #[ORM\Column(type: 'json')]
  public array $scopes = [];

  /**
   * Property expiry
   *
   * The expiry date and time.
   *
   * @var DateTimeImmutable
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $expiry;

  /**
   * Property isRevoked
   *
   * Whether the token is revoked.
   *
   * @var bool
   */
  #[ORM\Column(type: 'boolean')]
  public bool $isRevoked = false;
  //#endregion
}
