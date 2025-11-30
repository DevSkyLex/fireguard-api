<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Model;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Model AccessToken
 * @final
 *
 * Adapter for League AccessTokenEntityInterface.
 *
 * @category Model
 * @package Auth\Infrastructure\League\Model
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
