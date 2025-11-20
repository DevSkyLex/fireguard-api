<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * ValueObject Scopes
 * @final
 *
 * Represents a collection of OAuth 2.0 scopes.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements IteratorAggregate<int, Scope>
 */
final readonly class Scopes implements Countable, IteratorAggregate
{
  //#region Properties
  /**
   * Property scopes
   *
   * The collection of scopes.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<Scope> $scopes
   */
  private array $scopes;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the Scopes class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scope ...$scopes The scopes.
   */
  public function __construct(Scope ...$scopes)
  {
    if (empty($scopes)) {
      throw InvalidValueException::because(
        message: 'At least one scope is required.'
      );
    }

    // Remove duplicates
    $unique = [];
    foreach ($scopes as $scope) {
      $unique[$scope->value] = $scope;
    }

    $this->scopes = array_values($unique);
  }
  /**
   * Method fromArray
   * @static
   *
   * Creates a Scopes collection from an array of strings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string> $scopes The scopes.
   *
   * @return self The Scopes collection.
   */
  public static function fromArray(array $scopes): self
  {
    if (empty($scopes)) {
      throw InvalidValueException::because(
        message: 'At least one scope is required.'
      );
    }

    $scopeObjects = array_map(fn(string $value): Scope => new Scope($value), $scopes);

    return new self(...$scopeObjects);
  }
  //#endregion

  //#region Methods
  /**
   * Method fromString
   * @static
   *
   * Creates a Scopes collection from a space-separated string.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $scopesString Space-separated scopes (e.g., "openid profile email").
   *
   * @return self The Scopes collection.
   */
  public static function fromString(string $scopesString): self
  {
    $scopeValues = array_filter(array_map('trim', explode(' ', $scopesString)));

    if (empty($scopeValues)) {
      throw InvalidValueException::because(
        message: 'Scopes string cannot be empty.'
      );
    }

    $scopes = array_map(fn(string $value): Scope => new Scope($value), $scopeValues);

    return new self(...$scopes);
  }

  /**
   * Method contains
   *
   * Checks if the collection contains a specific scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scope $scope The scope to check.
   *
   * @return bool True if the scope is in the collection, false otherwise.
   */
  public function contains(Scope $scope): bool
  {
    foreach ($this->scopes as $s) {
      if ($s->equals($scope)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Method toString
   *
   * Returns a space-separated string of scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The space-separated scopes.
   */
  public function toString(): string
  {
    return implode(' ', array_map(fn(Scope $s): string => $s->value, $this->scopes));
  }

  /**
   * Method toArray
   *
   * Returns an array of scope values.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The scope values.
   */
  public function toArray(): array
  {
    return array_map(fn(Scope $s): string => $s->value, $this->scopes);
  }

  /**
   * Method count
   *
   * Returns the number of scopes in the collection.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The number of scopes.
   */
  public function count(): int
  {
    return count($this->scopes);
  }

  /**
   * Method getIterator
   *
   * Returns an iterator for the scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Traversable<int, Scope> The iterator.
   */
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->scopes);
  }
  //#endregion
}
