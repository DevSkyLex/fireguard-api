<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject UserId
 * @final
 *
 * Represents a unique identifier for a User.
 *
 * @category ValueObject
 * @package User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserId extends Uuid
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserId class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $value The UUID value.
   *
   * @throws InvalidValueException If the UUID is invalid.
   */
  public function __construct(?string $value = null)
  {
    parent::__construct(value: $value);
  }

  /**
   * Method generate
   *
   * Generates a new UserId.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self A new UserId instance.
   */
  public static function generate(): self
  {
    return new self(value: parent::generate()->value);
  }
  //#endregion
}
