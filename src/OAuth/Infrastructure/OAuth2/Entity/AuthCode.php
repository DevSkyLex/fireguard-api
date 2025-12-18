<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Entity AuthCode
 * @final
 *
 * League OAuth2 AuthCode entity implementation.
 *
 * @category Entity
 * @package OAuth\Infrastructure\OAuth2\Entity
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthCode implements AuthCodeEntityInterface
{
  use AuthCodeTrait;
  use EntityTrait;
  use TokenEntityTrait;
}
