<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Service;

use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Verified user identifier passed to the OAuth HTTP protocol implementation.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationUser implements UserEntityInterface
{
  /**
   * @param non-empty-string $identifier the verified account identifier
   */
  public function __construct(private string $identifier)
  {
  }

  public function getIdentifier(): string
  {
    return $this->identifier;
  }
}
