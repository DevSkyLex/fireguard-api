<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Contract\Federation\FederatedFlow;
use Auth\Application\Port\Outbound\Federation\FederatedFlowRepositoryPort;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Persistence\Doctrine\Record\FederatedAuthFlowRecord;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash;
use function hash_equals;

/**
 * Class FederatedFlowRepository
 *
 * Persists federated flow state and consumes it once after validating its expiry, provider, intent and browser binding.
 * The provider code verifier is encrypted at rest and the state row is locked during consumption.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedFlowRepository implements FederatedFlowRepositoryPort
{
  // #region Traits
  use CryptTrait;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Configures the auth entity manager and encryption key used to protect provider PKCE verifiers at rest.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the auth entity manager
   * @param string $encryptionKey the key used to encrypt the provider code verifier
   *
   * @return void
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
   *
   * Stores a federated authentication flow with its code verifier encrypted.
   *
   * @access public
   *
   * @param FederatedFlow $flow the flow state to persist
   *
   * @return void no return value
   */
  public function save(FederatedFlow $flow): void
  {
    $record = new FederatedAuthFlowRecord();
    $record->stateHash = $flow->stateHash;
    $record->browserBindingHash = $flow->browserBindingHash;
    $record->provider = $flow->provider->value;
    $record->intent = $flow->intent;
    $record->userId = $flow->userId;
    $record->codeVerifier = $this->encrypt($flow->codeVerifier);
    $record->redirectUri = $flow->redirectUri;
    $record->returnUrl = $flow->returnUrl;
    $record->expiresAt = $flow->expiresAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method consume
   *
   * Loads and consumes a matching unexpired federated flow under a transaction lock.
   *
   * @access public
   *
   * @param string $rawState the state value returned by the provider
   * @param string $browserBinding the browser binding value from the flow cookie
   * @param FederatedProvider $provider the expected identity provider
   * @param string $intent the expected sign-in or link intent
   *
   * @return FederatedFlow|null the consumed flow when all values match
   */
  public function consume(string $rawState, string $browserBinding, FederatedProvider $provider, string $intent): ?FederatedFlow
  {
    return $this->entityManager->wrapInTransaction(function () use ($rawState, $browserBinding, $provider, $intent): ?FederatedFlow {
      $record = $this->entityManager->find(
        FederatedAuthFlowRecord::class,
        hash('sha256', $rawState),
        LockMode::PESSIMISTIC_WRITE,
      );

      if (
        !$record instanceof FederatedAuthFlowRecord
        || null !== $record->consumedAt
        || $record->expiresAt <= new DateTimeImmutable()
        || $record->provider !== $provider->value
        || $record->intent !== $intent
        || !hash_equals($record->browserBindingHash, hash('sha256', $browserBinding))
      ) {
        return null;
      }

      $record->consumedAt = new DateTimeImmutable();
      $this->entityManager->flush();

      return new FederatedFlow(
        stateHash: $record->stateHash,
        browserBindingHash: $record->browserBindingHash,
        provider: $provider,
        intent: $record->intent,
        userId: $record->userId,
        codeVerifier: $this->decrypt($record->codeVerifier),
        redirectUri: $record->redirectUri,
        returnUrl: $record->returnUrl,
        expiresAt: $record->expiresAt,
      );
    });
  }
  // #endregion
}
