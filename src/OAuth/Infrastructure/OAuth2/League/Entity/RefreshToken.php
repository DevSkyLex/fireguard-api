<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Entity;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\{EntityTrait, RefreshTokenTrait};

/**
 * Entity RefreshToken.
 *
 * @category Entity
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken implements RefreshTokenEntityInterface
{
  /**
   * Trait EntityTrait.
   *
   * Entity trait implementation.
   *
   * @since 1.0.0
   * @see EntityTrait
   */
  use EntityTrait;

  // #region Traits
  /**
   * Trait RefreshTokenTrait.
   *
   * Refresh token trait implementation.
   *
   * @since 1.0.0
   * @see RefreshTokenTrait
   */
  use RefreshTokenTrait;
  // #endregion
}
