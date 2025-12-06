<?php

declare(strict_types=1);

namespace Auth\Infrastructure\OAuth2\Entity;

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
 * @package Auth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken implements RefreshTokenEntityInterface
{
  use RefreshTokenTrait;
  use EntityTrait;
}
