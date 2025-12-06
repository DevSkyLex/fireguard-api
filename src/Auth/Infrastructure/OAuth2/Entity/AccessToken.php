<?php

declare(strict_types=1);

namespace Auth\Infrastructure\OAuth2\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Entity AccessToken
 * @final
 *
 * League OAuth2 AccessToken entity implementation.
 *
 * @category Entity
 * @package Auth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AccessToken implements AccessTokenEntityInterface
{
  use AccessTokenTrait;
  use EntityTrait;
  use TokenEntityTrait;
}
