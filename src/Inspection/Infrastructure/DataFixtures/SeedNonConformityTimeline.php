<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use DateTimeImmutable;

/**
 * Creation, update and optional resolution dates for a seeded non-conformity.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedNonConformityTimeline
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $dueAt = null,
    public ?DateTimeImmutable $resolvedAt = null,
  ) {
  }
}
