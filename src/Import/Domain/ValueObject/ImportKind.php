<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function preg_match;

/**
 * Class ImportKind
 *
 * Retains the persisted kind independently from the current contributor registry.
 * The representation fits the existing sixteen-character database column.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportKind
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access private
   *
   * @param string $value valid persisted kind
   *
   * @return void
   */
  private function __construct(public string $value)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method from
   *
   * Validates representation only; application creation separately requires registration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value persisted kind
   *
   * @return self validated kind
   */
  public static function from(string $value): self
  {
    if (1 !== preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $value)) {
      throw InvalidValueException::because('Invalid import kind: ' . $value);
    }

    return new self($value);
  }

  /**
   * Method tryFrom
   *
   * @access public
   *
   * @param string $value persisted kind
   *
   * @return ?self validated kind, absent for an invalid representation
   */
  public static function tryFrom(string $value): ?self
  {
    return 1 === preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $value) ? new self($value) : null;
  }
  // #endregion
}
