<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use function sprintf;

/**
 * Exception EntityNotFoundException.
 *
 * Thrown when an entity is not found.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class EntityNotFoundException extends DomainException
{
    // #region Methods
    /**
     * Method forId.
     *
     * Creates an exception for an entity not found by ID.
     *
     * @since 1.0.0
     *
     * @param string $entityType the type of entity
     * @param string $id         the entity ID
     *
     * @return self the exception instance
     */
    public static function forId(string $entityType, string $id): self
    {
        return new self(
            message: sprintf('%s with ID "%s" not found.', $entityType, $id)
        );
    }

    /**
     * Method forCriteria.
     *
     * Creates an exception for an entity not found by criteria.
     *
     * @since 1.0.0
     *
     * @param string $entityType the type of entity
     * @param string $criteria   the search criteria description
     *
     * @return self the exception instance
     */
    public static function forCriteria(string $entityType, string $criteria): self
    {
        return new self(
            message: sprintf('%s not found for criteria: %s', $entityType, $criteria)
        );
    }
    // #endregion
}
