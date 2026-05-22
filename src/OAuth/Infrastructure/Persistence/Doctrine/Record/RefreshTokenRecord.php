<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record RefreshTokenRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_token_expiry', columns: ['expiry'])]
class RefreshTokenRecord
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
   * Property accessTokenIdentifier.
   *
   * The access token identifier.
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $accessTokenIdentifier;

  /**
   * Property clientIdentifier.
   *
   * The client identifier.
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $clientIdentifier;

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
   * Whether the token is revoked.
   */
  #[ORM\Column(type: 'boolean')]
  public bool $isRevoked = false;
  // #endregion
}
