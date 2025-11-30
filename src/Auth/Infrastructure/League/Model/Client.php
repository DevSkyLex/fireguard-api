<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Model;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * Model Client
 * @final
 *
 * Adapter for League ClientEntityInterface.
 *
 * @category Model
 * @package Auth\Infrastructure\League\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client implements ClientEntityInterface
{
  use ClientTrait;
  use EntityTrait;

  /**
   * Constructor
   *
   * @param string $identifier The client identifier.
   * @param string $name The client name.
   * @param string|array<string> $redirectUri The redirect URI(s).
   * @param bool $isConfidential Whether the client is confidential.
   */
  public function __construct(
    string $identifier,
    string $name,
    string|array $redirectUri,
    bool $isConfidential = true
  ) {
    assert(!empty($identifier));
    $this->setIdentifier($identifier);
    $this->name = $name;
    $this->redirectUri = $redirectUri;
    $this->isConfidential = $isConfidential;
  }
}
