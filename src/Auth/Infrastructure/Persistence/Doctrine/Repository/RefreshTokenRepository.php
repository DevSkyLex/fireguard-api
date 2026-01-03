<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Infrastructure\Persistence\Doctrine\Record\RefreshTokenRecord;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Repository RefreshTokenRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryPort
{
  use CryptTrait;

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
    #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')]
    string $encryptionKey,
  ) {
    $this->setEncryptionKey($encryptionKey);
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
      id: $refreshToken->identifier(),
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
      id: $identifier,
    );

    if (!$record) {
      return null;
    }

    return new RefreshToken(
      identifier: $record->identifier,
      expiryDateTime: $record->expiry,
      accessTokenIdentifier: $record->accessTokenIdentifier,
      clientIdentifier: new OAuthClientIdentifier($record->clientIdentifier),
      isRevoked: $record->isRevoked,
    );
  }

  public function findByEncryptedToken(string $encryptedToken): ?RefreshToken
  {
    if ('' === $encryptedToken) {
      return null;
    }

    try {
      $decrypted = $this->decrypt($encryptedToken);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return null;
      }

      $identifier = $payload['refresh_token_id'] ?? null;
      if (!is_string($identifier) || '' === $identifier) {
        return null;
      }

      return $this->find($identifier);
    } catch (Throwable) {
      return null;
    }
  }
  // #endregion
}
