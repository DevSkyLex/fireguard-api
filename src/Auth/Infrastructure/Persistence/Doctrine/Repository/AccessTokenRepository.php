<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Infrastructure\Persistence\Doctrine\Record\AccessTokenRecord;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Port\Outbound\AccessTokenRepositoryPort;
use OAuth\Domain\Model\AccessToken;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Repository AccessTokenRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AccessTokenRepository implements AccessTokenRepositoryPort
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * AccessTokenRepository class.
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
     * Saves an access token to the database.
     *
     * @since 1.0.0
     *
     * @param AccessToken $accessToken the access token to save
     */
    public function save(AccessToken $accessToken): void
    {
        $record = $this->entityManager->find(
            className: AccessTokenRecord::class,
            id: $accessToken->identifier()
        );

        if (!$record) {
            $record = new AccessTokenRecord();
            $record->identifier = $accessToken->identifier();
        }

        $record->clientIdentifier = (string) $accessToken->clientIdentifier();
        $record->userIdentifier = $accessToken->userIdentifier();
        $record->scopes = $accessToken->scopes()->toArray();
        $record->expiry = $accessToken->expiry();
        $record->isRevoked = $accessToken->isRevoked();

        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /**
     * Method find
     * {@inheritDoc}
     *
     * Finds an access token by its identifier.
     *
     * @since 1.0.0
     *
     * @param string $identifier the identifier of the access token to find
     */
    public function find(string $identifier): ?AccessToken
    {
        $record = $this->entityManager->find(
            className: AccessTokenRecord::class,
            id: $identifier
        );

        if (!$record) {
            return null;
        }

        return new AccessToken(
            identifier: $record->identifier,
            clientIdentifier: new OAuthClientIdentifier($record->clientIdentifier),
            expiry: $record->expiry,
            scopes: Scopes::fromArray($record->scopes),
            userIdentifier: $record->userIdentifier,
            isRevoked: $record->isRevoked
        );
    }
    // #endregion
}
