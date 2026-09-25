<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Client;

use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\GrantTypes;

/**
 * Settings restored from a persisted OAuth client.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredClientSettings
{
  /**
   * @param list<string> $redirectUris the stored redirect URIs
   */
  public function __construct(
    public array $redirectUris,
    public GrantTypes $grantTypes,
    public Scopes $scopes,
  ) {
  }
}
