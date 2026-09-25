<?php

declare(strict_types=1);

namespace Session\Domain\Model\Session;

use DateTimeImmutable;

/**
 * Timestamps restored from a persisted session.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredSessionLifecycle
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $lastActivityAt,
    public ?DateTimeImmutable $revokedAt,
  ) {
  }
}
