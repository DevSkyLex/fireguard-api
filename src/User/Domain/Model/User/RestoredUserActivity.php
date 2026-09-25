<?php

declare(strict_types=1);

namespace User\Domain\Model\User;

use DateTimeImmutable;
use User\Domain\ValueObject\Locale;

/**
 * Timestamps and preferences restored from a previously persisted user.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredUserActivity
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $lastLoginAt,
    public ?string $lastSignInMethod,
    public Locale $locale,
  ) {
  }
}
