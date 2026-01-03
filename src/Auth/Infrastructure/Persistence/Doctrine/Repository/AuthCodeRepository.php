<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Infrastructure\Persistence\Doctrine\Record\AuthCodeRecord;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Repository AuthCodeRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthCodeRepository implements AuthCodeRepositoryPort
{
  use CryptTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuthCodeRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
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
   * Saves an auth code to the database.
   *
   * @since 1.0.0
   *
   * @param AuthCode $authCode the auth code to save
   */
  public function save(AuthCode $authCode): void
  {
    $record = $this->entityManager->find(
      className: AuthCodeRecord::class,
      id: $authCode->identifier(),
    );

    if (!$record) {
      $record = new AuthCodeRecord();
      $record->identifier = $authCode->identifier();
    }

    $record->clientIdentifier = (string) $authCode->clientIdentifier();
    $record->userIdentifier = $authCode->userIdentifier();
    $record->scopes = $authCode->scopes()->toArray();
    $record->redirectUri = $authCode->redirectUri();
    $record->nonce = $authCode->nonce();
    $record->expiry = $authCode->expiryDateTime();
    $record->isRevoked = $authCode->isRevoked();

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method find
   * {@inheritDoc}
   *
   * Finds an auth code by its identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the identifier of the auth code to find
   */
  public function find(string $identifier): ?AuthCode
  {
    $record = $this->entityManager->find(
      className: AuthCodeRecord::class,
      id: $identifier,
    );

    if (!$record) {
      return null;
    }

    return new AuthCode(
      identifier: $record->identifier,
      expiryDateTime: $record->expiry,
      clientIdentifier: new OAuthClientIdentifier($record->clientIdentifier),
      userIdentifier: $record->userIdentifier,
      scopes: Scopes::fromArray($record->scopes),
      redirectUri: $record->redirectUri,
      nonce: $record->nonce,
      isRevoked: $record->isRevoked,
    );
  }

  public function findByEncryptedCode(string $encryptedCode): ?AuthCode
  {
    if ('' === $encryptedCode) {
      return null;
    }

    try {
      $decrypted = $this->decrypt($encryptedCode);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return null;
      }

      $identifier = $payload['auth_code_id'] ?? null;
      if (!is_string($identifier) || '' === $identifier) {
        return null;
      }

      return $this->find($identifier);
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method updateNonce
   * {@inheritDoc}
   *
   * Updates the nonce for an auth code.
   *
   * @since 1.0.0
   *
   * @param string $identifier the auth code identifier
   * @param string|null $nonce the nonce value
   *
   * @return void no return value
   */
  public function updateNonce(string $identifier, ?string $nonce): void
  {
    $resolvedIdentifier = $identifier;
    $decryptedIdentifier = $this->resolveEncryptedIdentifier($identifier);
    if (null !== $decryptedIdentifier) {
      $resolvedIdentifier = $decryptedIdentifier;
    }

    $record = $this->entityManager->find(
      className: AuthCodeRecord::class,
      id: $resolvedIdentifier,
    );

    if (!$record) {
      return;
    }

    $record->nonce = $nonce;
    $this->entityManager->flush();
  }

  private function resolveEncryptedIdentifier(string $encryptedCode): ?string
  {
    if ('' === $encryptedCode) {
      return null;
    }

    try {
      $decrypted = $this->decrypt($encryptedCode);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return null;
      }

      $identifier = $payload['auth_code_id'] ?? null;
      if (!is_string($identifier) || '' === $identifier) {
        return null;
      }

      return $identifier;
    } catch (Throwable) {
      return null;
    }
  }
  // #endregion
}
