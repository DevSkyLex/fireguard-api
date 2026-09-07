<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Contract FederatedConnections.
 *
 * Current sign-in methods for one user.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedConnections
{
  /**
   * @param list<FederatedConnection> $connections
   */
  public function __construct(
    public bool $passwordConfigured,
    public array $connections,
    public ?string $lastSignInMethod,
  ) {
  }
}
