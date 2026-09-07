<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;
use DateTimeImmutable;

/**
 * Contract FederatedConnection.
 *
 * A provider identity linked to one Fireguard user.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedConnection
{
  public function __construct(
    public string $id,
    public string $userId,
    public FederatedProvider $provider,
    public string $subject,
    public string $email,
    public DateTimeImmutable $connectedAt,
    public DateTimeImmutable $lastUsedAt,
  ) {
  }
}
