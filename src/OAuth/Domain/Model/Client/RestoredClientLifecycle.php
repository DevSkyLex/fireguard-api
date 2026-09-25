<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Client;

use DateTimeImmutable;

/**
 * Lifecycle restored from a persisted OAuth client.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredClientLifecycle
{
  public function __construct(
    public bool $isActive,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $deletedAt,
  ) {
  }
}
