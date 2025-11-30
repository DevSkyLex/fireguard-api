<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Model;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Model User
 * @final
 *
 * Adapter for League UserEntityInterface.
 *
 * @category Model
 * @package Auth\Infrastructure\League\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class User implements UserEntityInterface
{
  use EntityTrait;
}
