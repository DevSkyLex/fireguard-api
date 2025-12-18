<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Entity;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Entity User
 * @final
 *
 * League OAuth2 User entity implementation.
 *
 * @category Entity
 * @package OAuth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class User implements UserEntityInterface
{
  use EntityTrait;
}
