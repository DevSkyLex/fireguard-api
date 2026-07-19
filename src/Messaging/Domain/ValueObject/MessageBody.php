<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;

use function mb_strlen;
use function trim;

/**
 * ValueObject MessageBody.
 *
 * A sanitized, trimmed message body bounded to 4000 characters. Sanitization
 * (allow-listing rich-text markup) happens upstream, in the Presentation
 * layer, before the raw HTML reaches this value object; this VO enforces the
 * length and blank-body invariants shared by both message creation and
 * editing.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessageBody
{
  // #region Constants
  /**
   * Constant MAX_LENGTH.
   */
  private const int MAX_LENGTH = 4000;
  // #endregion

  // #region Properties
  /**
   * Property value.
   *
   * The validated, trimmed body.
   *
   * @since 1.0.0
   */
  public string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Use {@see self::fromString()} instead of instantiating directly.
   *
   * @since 1.0.0
   *
   * @param string $value the validated body value
   */
  private function __construct(string $value)
  {
    $this->value = $value;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Trims and validates a raw (already-sanitized) message body.
   *
   * @since 1.0.0
   *
   * @param string $value the raw body value
   *
   * @return self the validated message body
   */
  public static function fromString(string $value): self
  {
    $trimmed = trim($value);

    if ('' === $trimmed) {
      throw new MessagingValidationException('The message body must not be blank.');
    }

    if (mb_strlen($trimmed) > self::MAX_LENGTH) {
      throw new MessagingValidationException('The message body must not exceed 4000 characters.');
    }

    return new self($trimmed);
  }
  // #endregion
}
