<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Entity;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * Entity RefreshToken
 * @final
 *
 * League OAuth2 RefreshToken entity implementation.
 *
 * @category Entity
 * @package OAuth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken implements RefreshTokenEntityInterface
{
  //#region Traits
  /**
   * Trait RefreshTokenTrait
   * 
   * Refresh token trait implementation.
   * 
   * @since 1.0.0
   * @see RefreshTokenTrait
   */
  use RefreshTokenTrait;

  /**
   * Trait EntityTrait
   * 
   * Entity trait implementation.
   * 
   * @since 1.0.0
   * @see EntityTrait
   */
  use EntityTrait;
  //#endregion
}
