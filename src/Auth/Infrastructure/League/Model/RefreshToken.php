<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Model;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * Model RefreshToken
 * @final
 *
 * Adapter for League RefreshTokenEntityInterface.
 *
 * @category Model
 * @package Auth\Infrastructure\League\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken implements RefreshTokenEntityInterface
{
  use RefreshTokenTrait;
  use EntityTrait;
}
