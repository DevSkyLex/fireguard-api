<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * ValueObject GrantTypes
 * @final
 *
 * Represents a collection of OAuth 2.0 grant types.
 *
 * @category ValueObject
 * @package OAuth\Domain\ValueObject
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements IteratorAggregate<int, GrantType>
 */
final readonly class GrantTypes implements Countable, IteratorAggregate
{
  //#region Properties
  /**
   * Property grantTypes
   *
   * The collection of grant types.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<GrantType> $grantTypes
   */
  private array $grantTypes;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the GrantTypes class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantType ...$grantTypes The grant types.
   */
  public function __construct(GrantType ...$grantTypes)
  {
    if (empty($grantTypes)) {
      throw InvalidValueException::because(
        message: 'At least one grant type is required.'
      );
    }

    // Remove duplicates based on enum value
    $unique = [];
    foreach ($grantTypes as $grantType) {
      $unique[$grantType->value] = $grantType;
    }

    $this->grantTypes = array_values($unique);
  }

  /**
   * Method fromArray
   * @static
   *
   * Creates a GrantTypes collection from an array of strings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string> $grantTypes The grant types as strings.
   *
   * @return self The GrantTypes collection.
   * 
   * @throws \ValueError If any string is not a valid grant type.
   */
  public static function fromArray(array $grantTypes): self
  {
    if (empty($grantTypes)) {
      throw InvalidValueException::because(
        message: 'At least one grant type is required.'
      );
    }

    $types = array_map(fn(string $value): GrantType => GrantType::from($value), $grantTypes);

    return new self(...$types);
  }
  //#endregion

  //#region Methods
  /**
   * Method contains
   *
   * Checks if the collection contains a specific grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantType $grantType The grant type to check.
   *
   * @return bool True if the grant type is in the collection, false otherwise.
   */
  public function contains(GrantType $grantType): bool
  {
    foreach ($this->grantTypes as $gt) {
      if ($gt === $grantType) {
        return true;
      }
    }
    return false;
  }

  /**
   * Method toArray
   *
   * Returns an array of grant type values.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The grant type values.
   */
  public function toArray(): array
  {
    return array_map(fn(GrantType $gt) => $gt->value, $this->grantTypes);
  }

  /**
   * Method count
   *
   * Returns the number of grant types in the collection.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The number of grant types.
   */
  public function count(): int
  {
    return count($this->grantTypes);
  }

  /**
   * Method getIterator
   *
   * Returns an iterator for the grant types.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Traversable<int, GrantType> The iterator.
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->grantTypes);
  }
  //#endregion
}
