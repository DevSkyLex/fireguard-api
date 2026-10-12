<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function preg_match;

/**
 * ValueObject MessagingSubjectType.
 *
 * Preserves a subject key without requiring its contributor to be loaded.
 * Registration and access checks belong to the application catalog; channel
 * and direct remain reserved participant conversation kinds.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingSubjectType
{
  // #region Constants
  /**
   * Constant CHANNEL.
   */
  public const string CHANNEL = 'channel';

  /**
   * Constant DIRECT.
   */
  public const string DIRECT = 'direct';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @access private
   *
   * @param string $value the unchanged persisted subject key
   */
  private function __construct(public string $value)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method from.
   *
   * Validates the representation within the existing varchar(32) column.
   * It never normalizes or checks whether an owner contributor is registered.
   *
   * @access public
   *
   * @param string $value the wire or persisted subject key
   *
   * @return self the subject key
   *
   * @throws InvalidValueException when the key is malformed
   */
  public static function from(string $value): self
  {
    if (1 !== preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D', $value)) {
      throw InvalidValueException::because('A messaging subject type must contain 1 to 32 lowercase letters, digits or underscores and start with a letter.');
    }

    return new self($value);
  }
  // #endregion
}
