<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject\Security;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Shared\Domain\Exception\InvalidValueException;
use Traversable;
use ValueError;

use function array_map;
use function array_values;
use function count;

/**
 * ValueObject GrantTypes.
 *
 * @category ValueObject
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements IteratorAggregate<int, GrantType>
 */
final readonly class GrantTypes implements Countable, IteratorAggregate
{
  // #region Properties
  /**
   * Property grantTypes.
   *
   * The collection of grant types.
   *
   * @since 1.0.0
   *
   * @var array<GrantType>
   */
  private array $grantTypes;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GrantTypes class.
   *
   * @since 1.0.0
   *
   * @param GrantType ...$grantTypes The grant types.
   */
  public function __construct(GrantType ...$grantTypes)
  {
    if (empty($grantTypes)) {
      throw InvalidValueException::because(
        message: 'At least one grant type is required.',
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
   * Method fromArray.
   *
   * @static
   *
   * Creates a GrantTypes collection from an array of strings.
   *
   * @since 1.0.0
   *
   * @param array<string> $grantTypes the grant types as strings
   *
   * @throws ValueError if any string is not a valid grant type
   *
   * @return self the GrantTypes collection
   */
  public static function fromArray(array $grantTypes): self
  {
    if (empty($grantTypes)) {
      throw InvalidValueException::because(
        message: 'At least one grant type is required.',
      );
    }

    $types = array_map(fn (string $value): GrantType => GrantType::from($value), $grantTypes);

    return new self(...$types);
  }
  // #endregion

  // #region Methods
  /**
   * Method contains.
   *
   * Checks if the collection contains a specific grant type.
   *
   * @since 1.0.0
   *
   * @param GrantType $grantType the grant type to check
   *
   * @return bool true if the grant type is in the collection, false otherwise
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
   * Method toArray.
   *
   * Returns an array of grant type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the grant type values
   */
  public function toArray(): array
  {
    return array_map(fn (GrantType $gt) => $gt->value, $this->grantTypes);
  }

  /**
   * Method count.
   *
   * Returns the number of grant types in the collection.
   *
   * @since 1.0.0
   *
   * @return int the number of grant types
   */
  public function count(): int
  {
    return count($this->grantTypes);
  }

  /**
   * Method getIterator.
   *
   * Returns an iterator for the grant types.
   *
   * @since 1.0.0
   *
   * @return Traversable<int, GrantType> the iterator
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->grantTypes);
  }
  // #endregion
}
