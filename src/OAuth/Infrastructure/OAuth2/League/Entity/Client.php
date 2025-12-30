<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

use function assert;

/**
 * Entity Client.
 *
 * @category Entity
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client implements ClientEntityInterface
{
  use ClientTrait;
  use EntityTrait;

  /**
   * Constructor.
   *
   * @param string $identifier the client identifier
   * @param string $name the client name
   * @param string|array<string> $redirectUri the redirect URI(s)
   * @param bool $isConfidential whether the client is confidential
   */
  public function __construct(
    string $identifier,
    string $name,
    string|array $redirectUri,
    bool $isConfidential = true,
  ) {
    assert(!empty($identifier));
    $this->setIdentifier($identifier);
    $this->name = $name;
    $this->redirectUri = $redirectUri;
    $this->isConfidential = $isConfidential;
  }
}
