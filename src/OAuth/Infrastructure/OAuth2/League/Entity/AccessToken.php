<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\{AccessTokenTrait, EntityTrait, TokenEntityTrait};

/**
 * Entity AccessToken.
 *
 * @category Entity
 *
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
