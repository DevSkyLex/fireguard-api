<?php

declare(strict_types=1);

namespace Auth\Infrastructure\OAuth2\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * Entity Scope
 * @final
 *
 * League OAuth2 Scope entity implementation.
 *
 * @category Entity
 * @package Auth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Scope implements ScopeEntityInterface
{
  use EntityTrait;
  use ScopeTrait;
}
