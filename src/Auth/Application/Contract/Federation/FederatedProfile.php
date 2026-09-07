<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;

/**
 * Contract FederatedProfile.
 *
 * Normalized identity returned by a provider after the code exchange.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedProfile
{
  public function __construct(
    public FederatedProvider $provider,
    public string $subject,
    public string $email,
    public bool $emailVerified,
    public string $firstName,
    public string $lastName,
  ) {
  }
}
