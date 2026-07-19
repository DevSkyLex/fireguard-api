<?php

declare(strict_types=1);

namespace Assistant\Domain\ValueObject;

use function array_column;

/**
 * Enum AssistantMessageRole.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum AssistantMessageRole: string
{
  case USER = 'user';
  case ASSISTANT = 'assistant';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported assistant message role values.
   *
   * @since 1.0.0
   *
   * @return list<string> the assistant message role values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
