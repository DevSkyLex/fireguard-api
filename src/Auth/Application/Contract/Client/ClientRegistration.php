<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Client;

/**
 * Stable OAuth client registration projection.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRegistration
{
  /** @param list<string> $redirectUris registered redirect URIs
   * @param list<string> $grantTypes supported grant values
   * @param list<string> $scopes supported scope values
   */
  public function __construct(public string $identifier, public string $name, public array $redirectUris, public array $grantTypes, public array $scopes)
  {
  }
}
