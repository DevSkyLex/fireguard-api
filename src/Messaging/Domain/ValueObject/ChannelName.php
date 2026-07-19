<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Stringable;

use function mb_strlen;
use function preg_match;
use function trim;

/**
 * ValueObject ChannelName.
 *
 * A channel's display name (spaces allowed), mirroring
 * `Organization\Domain\ValueObject\TeamName`: free-form text shown in the
 * UI, not an RBAC slug. Only enforces a trimmed length bound and rejects
 * control characters.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChannelName implements Stringable
{
  // #region Constants
  private const string CONTROL_CHARS_PATTERN = '/[\x00-\x1F\x7F]/';
  // #endregion

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $value the raw channel name
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ('' === $normalized) {
      throw new MessagingValidationException('Channel name cannot be empty.');
    }

    $length = mb_strlen($normalized);
    if ($length < 2 || $length > 80) {
      throw new MessagingValidationException('Channel name must be between 2 and 80 characters.');
    }

    if (1 === preg_match(self::CONTROL_CHARS_PATTERN, $normalized)) {
      throw new MessagingValidationException('Channel name cannot contain control characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized channel name.
   *
   * @since 1.0.0
   *
   * @return string the normalized channel name
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two channel names are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the name to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
