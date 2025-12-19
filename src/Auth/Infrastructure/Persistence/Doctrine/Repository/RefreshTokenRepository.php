<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Infrastructure\Persistence\Doctrine\Record\RefreshTokenRecord;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\RefreshToken;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Repository RefreshTokenRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenRepository implements RefreshTokenRepositoryPort
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * RefreshTokenRepository class.
     *
     * @since 1.0.0
     *
     * @param EntityManagerInterface $entityManager the entity manager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method save
     * {@inheritDoc}
     *
     * Saves a refresh token to the database.
     *
     * @since 1.0.0
     *
     * @param RefreshToken $refreshToken the refresh token to save
     */
    public function save(RefreshToken $refreshToken): void
    {
        $record = $this->entityManager->find(
            className: RefreshTokenRecord::class,
            id: $refreshToken->identifier()
        );

        if (!$record) {
            $record = new RefreshTokenRecord();
            $record->identifier = $refreshToken->identifier();
        }

        $record->accessTokenIdentifier = $refreshToken->accessTokenIdentifier();
        $record->clientIdentifier = (string) $refreshToken->clientIdentifier();
        $record->expiry = $refreshToken->expiryDateTime();
        $record->isRevoked = $refreshToken->isRevoked();

        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /**
     * Method find
     * {@inheritDoc}
     *
     * Finds a refresh token by its identifier.
     *
     * @since 1.0.0
     *
     * @param string $identifier the identifier of the refresh token to find
     */
    public function find(string $identifier): ?RefreshToken
    {
        $record = $this->entityManager->find(
            className: RefreshTokenRecord::class,
            id: $identifier
        );

        if (!$record) {
            return null;
        }

        return new RefreshToken(
            identifier: $record->identifier,
            expiryDateTime: $record->expiry,
            accessTokenIdentifier: $record->accessTokenIdentifier,
            clientIdentifier: new OAuthClientIdentifier($record->clientIdentifier),
            isRevoked: $record->isRevoked
        );
    }
    // #endregion
}
