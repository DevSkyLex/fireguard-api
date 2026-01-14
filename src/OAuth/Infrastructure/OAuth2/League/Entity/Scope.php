<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\{EntityTrait, ScopeTrait};

/**
 * Entity Scope.
 *
 * @category Entity
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Scope implements ScopeEntityInterface
{
  use EntityTrait;
  use ScopeTrait;
}
