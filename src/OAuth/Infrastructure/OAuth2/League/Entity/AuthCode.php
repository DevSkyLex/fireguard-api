<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\{AuthCodeTrait, EntityTrait, TokenEntityTrait};

/**
 * Entity AuthCode.
 *
 * @category Entity
 *
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
