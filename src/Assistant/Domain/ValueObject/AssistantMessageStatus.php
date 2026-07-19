<?php

declare(strict_types=1);

namespace Assistant\Domain\ValueObject;

use function array_column;
use function in_array;

/**
 * Enum AssistantMessageStatus.
 *
 * The assistant message generation state machine. A `user`-authored message
 * is created directly as {@see self::COMPLETE} (it is never generated), while
 * an `assistant`-authored reply is created as {@see self::PENDING} the moment
 * it is enqueued for generation and only ever moves forward:
 *
 * ```
 * PENDING --> STREAMING --> COMPLETE
 *    |                          ^
 *    `------> FAILED <----------'
 * ```
 *
 * `PENDING` may fail directly (`FAILED`) without ever reaching `STREAMING` —
 * e.g. the inference backend is unreachable before the first token arrives.
 * {@see self::COMPLETE} and {@see self::FAILED} are terminal: once reached, a
 * Messenger retry on the generation command must never re-append a second
 * assistant message for the same turn, it must be rejected by
 * {@see \Assistant\Domain\Model\Message\AssistantMessage} instead.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum AssistantMessageStatus: string
{
  case PENDING = 'pending';
  case STREAMING = 'streaming';
  case COMPLETE = 'complete';
  case FAILED = 'failed';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported assistant message status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the assistant message status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method canTransitionTo.
   *
   * Reports whether transitioning from this status to the given target
   * status is legal. The single source of truth for the state machine
   * diagrammed on this enum's docblock.
   *
   * @since 1.0.0
   *
   * @param self $target the candidate target status
   *
   * @return bool true when the transition is legal
   */
  public function canTransitionTo(self $target): bool
  {
    return match ($this) {
      self::PENDING => in_array($target, [self::STREAMING, self::FAILED], true),
      self::STREAMING => in_array($target, [self::COMPLETE, self::FAILED], true),
      self::COMPLETE, self::FAILED => false,
    };
  }

  /**
   * Method isTerminal.
   *
   * @since 1.0.0
   *
   * @return bool true when no further transition is ever legal
   */
  public function isTerminal(): bool
  {
    return self::COMPLETE === $this || self::FAILED === $this;
  }
  // #endregion
}
