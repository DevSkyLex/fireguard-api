<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use DateTimeImmutable;

/**
 * Creation, update and optional resolution dates for a seeded non-conformity.
 *
 * @category DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedNonConformityTimeline
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Provides lifecycle timestamps for a seeded non-conformity.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt creation timestamp
   * @param DateTimeImmutable $updatedAt most recent update timestamp
   * @param ?DateTimeImmutable $dueAt optional due date
   * @param ?DateTimeImmutable $resolvedAt optional resolution timestamp
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $dueAt = null,
    public ?DateTimeImmutable $resolvedAt = null,
  ) {
  }
  // #endregion
}
