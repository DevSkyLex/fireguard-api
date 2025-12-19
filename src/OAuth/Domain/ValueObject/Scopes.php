<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Shared\Domain\Exception\InvalidValueException;
use Traversable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;

/**
 * ValueObject Scopes.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements IteratorAggregate<int, Scope>
 */
final readonly class Scopes implements Countable, IteratorAggregate
{
    // #region Properties
    /**
     * Property scopes.
     *
     * The collection of scopes.
     *
     * @since 1.0.0
     *
     * @var array<Scope>
     */
    private array $scopes;
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the Scopes class.
     *
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
     * Method fromArray.
     *
     * @static
     *
     * Creates a Scopes collection from
     * an array of strings.
     *
     * @since 1.0.0
     *
     * @param array<string> $scopes the scopes
     *
     * @return self the Scopes collection
     */
    public static function fromArray(array $scopes): self
    {
        if (empty($scopes)) {
            throw InvalidValueException::because(
                message: 'At least one scope is required.'
            );
        }

        $scopeObjects = [];
        foreach ($scopes as $value) {
            $scope = Scope::tryFrom($value);
            if (null === $scope) {
                throw InvalidValueException::because(
                    message: "Invalid scope: $value"
                );
            }
            $scopeObjects[] = $scope;
        }

        return new self(...$scopeObjects);
    }
    // #endregion

    // #region Methods
    /**
     * Method fromString.
     *
     * @static
     *
     * Creates a Scopes collection from a
     * space-separated string.
     *
     * @since 1.0.0
     *
     * @param string $scopesString Space-separated scopes (e.g., "openid profile email").
     *
     * @return self the Scopes collection
     */
    public static function fromString(string $scopesString): self
    {
        $scopeValues = array_filter(array_map('trim', explode(' ', $scopesString)));

        if (empty($scopeValues)) {
            throw InvalidValueException::because(
                message: 'Scopes string cannot be empty.'
            );
        }

        $scopes = [];
        foreach ($scopeValues as $value) {
            $scope = Scope::tryFrom($value);
            if (null === $scope) {
                throw InvalidValueException::because(
                    message: "Invalid scope: $value"
                );
            }
            $scopes[] = $scope;
        }

        return new self(...$scopes);
    }

    /**
     * Method contains.
     *
     * Checks if the collection contains
     * a specific scope.
     *
     * @since 1.0.0
     *
     * @param Scope $scope the scope to check
     *
     * @return bool true if the scope is in the collection, false otherwise
     */
    public function contains(Scope $scope): bool
    {
        foreach ($this->scopes as $s) {
            if ($s === $scope) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method toString.
     *
     * Returns a space-separated string of scopes.
     *
     * @since 1.0.0
     *
     * @return string the space-separated scopes
     */
    public function toString(): string
    {
        return implode(' ', array_map(fn (Scope $s): string => $s->value, $this->scopes));
    }

    /**
     * Method toArray.
     *
     * Returns an array of scope values.
     *
     * @since 1.0.0
     *
     * @return list<string> the scope values
     */
    public function toArray(): array
    {
        return array_map(fn (Scope $s): string => $s->value, $this->scopes);
    }

    /**
     * Method count.
     *
     * Returns the number of scopes in the collection.
     *
     * @since 1.0.0
     *
     * @return int the number of scopes
     */
    public function count(): int
    {
        return count($this->scopes);
    }

    /**
     * Method getIterator.
     *
     * Returns an iterator for the scopes.
     *
     * @since 1.0.0
     *
     * @return Traversable<int, Scope> the iterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->scopes);
    }
    // #endregion
}
