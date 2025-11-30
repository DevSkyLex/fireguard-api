<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record RefreshTokenRecord
 * @final
 *
 * Doctrine entity for Refresh Token persistence.
 *
 * @category Record
 * @package Auth\Infrastructure\Persistence\Doctrine\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
final class RefreshTokenRecord
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
   * Property accessTokenIdentifier
   *
   * The access token identifier.
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $accessTokenIdentifier;

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
