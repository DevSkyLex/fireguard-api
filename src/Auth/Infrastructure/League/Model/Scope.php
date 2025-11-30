<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Model;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * Model Scope
 * @final
 *
 * Adapter for League ScopeEntityInterface.
 *
 * @category Model
 * @package Auth\Infrastructure\League\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Scope implements ScopeEntityInterface
{
  use EntityTrait;
  use ScopeTrait;
}
