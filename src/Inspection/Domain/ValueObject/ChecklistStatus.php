<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum ChecklistStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ChecklistStatus: string
{
  case ACTIVE = 'active';
  case ARCHIVED = 'archived';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported checklist status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the checklist status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isArchived.
   *
   * @since 1.0.0
   */
  public function isArchived(): bool
  {
    return self::ARCHIVED === $this;
  }

  public function label(): string
  {
    return match ($this) {
      self::ACTIVE => 'Active',
      self::ARCHIVED => 'Archived',
    };
  }
  // #endregion
}
