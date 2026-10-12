<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Repository;

use Auth\Application\Contract\Federation\{FederatedFlow, FederatedProfile, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedFlowRepositoryPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Persistence\Doctrine\Record\FederatedAuthFlowRecord;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash;
use function hash_equals;
use function is_array;
use function is_bool;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

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

  // #region Properties
  /**
   * Constant INVALID_FLOW_MESSAGE
   *
   * Preserves the shared client-facing rejection for unusable retained flow proof.
   */
  private const string INVALID_FLOW_MESSAGE = 'This connection request is no longer valid.';
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
    $record->nonceHash = $flow->nonceHash;
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
   * @param ProviderId $provider the expected identity provider
   * @param string $intent the expected sign-in or link intent
   *
   * @return FederatedFlow|null the consumed flow when all values match
   */
  public function consume(string $rawState, string $browserBinding, ProviderId $provider, string $intent): ?FederatedFlow
  {
    return $this->entityManager->wrapInTransaction(function () use ($rawState, $browserBinding, $provider, $intent): ?FederatedFlow {
      $record = $this->entityManager->find(
        FederatedAuthFlowRecord::class,
        hash('sha256', $rawState),
        LockMode::PESSIMISTIC_WRITE,
      );

      if (
        !$record instanceof FederatedAuthFlowRecord
        || $record->expiresAt <= new DateTimeImmutable()
        || $record->provider !== $provider->value
        || $record->intent !== $intent
        || !hash_equals($record->browserBindingHash, hash('sha256', $browserBinding))
      ) {
        return null;
      }

      if ('provider_verified' === $record->stage && null !== $record->proofExpiresAt && $record->proofExpiresAt > new DateTimeImmutable()) {
        return $this->project($record);
      }

      return $this->consumeReserved($record);
    });
  }

  /**
   * {@inheritDoc}
   */
  public function inspect(string $rawState, string $browserBinding, ProviderId $provider, string $intent): ?FederatedFlow
  {
    $record = '' === $rawState || '' === $browserBinding
      ? null
      : $this->entityManager->find(FederatedAuthFlowRecord::class, hash('sha256', $rawState));
    if (!$record instanceof FederatedAuthFlowRecord) {
      return null;
    }
    $this->entityManager->refresh($record);
    if (
      $record->expiresAt <= new DateTimeImmutable()
      || $record->provider !== $provider->value
      || $record->intent !== $intent
      || !hash_equals($record->browserBindingHash, hash('sha256', $browserBinding))
      || ('completed' !== $record->stage && null !== $record->proofExpiresAt && $record->proofExpiresAt <= new DateTimeImmutable())
    ) {
      return null;
    }

    // Inspection proves browser/state/lifecycle ownership without opening provider or PKCE secrets.
    // Completed expired receipts still run their current account/session gate before the engine checks TTL.
    return $this->project($record, false);
  }

  /**
   * {@inheritDoc}
   */
  public function recordVerified(string $stateHash, FederatedProfile $profile): void
  {
    $this->entityManager->wrapInTransaction(function () use ($stateHash, $profile): void {
      $record = $this->entityManager->find(FederatedAuthFlowRecord::class, $stateHash, LockMode::PESSIMISTIC_WRITE);
      if (!$record instanceof FederatedAuthFlowRecord || 'reserved' !== $record->stage || null === $record->exchangeStartedAt || $record->provider !== $profile->provider->value || !$profile->emailVerified) {
        throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      }
      $record->verifiedProfile = $this->encrypt(json_encode([
        'provider' => $profile->provider->value,
        'subject' => $profile->subject,
        'email' => $profile->email,
        'email_verified' => $profile->emailVerified,
        'first_name' => $profile->firstName,
        'last_name' => $profile->lastName,
        'issuer' => $profile->issuer,
      ], JSON_THROW_ON_ERROR));
      $record->stage = 'provider_verified';
      $record->proofExpiresAt = new DateTimeImmutable('+5 minutes');
      $this->entityManager->flush();
    });
  }

  /**
   * {@inheritDoc}
   */
  public function markCompleted(string $stateHash, string $userId): void
  {
    if (!$this->entityManager->getConnection()->isTransactionActive()) {
      throw new LogicException('Federated completion must share its owning auth transaction.');
    }
    $record = $this->entityManager->find(FederatedAuthFlowRecord::class, $stateHash, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof FederatedAuthFlowRecord || 'provider_verified' !== $record->stage || null === $record->proofExpiresAt || $record->proofExpiresAt <= new DateTimeImmutable()) {
      throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
    }
    $record->stage = 'completed';
    $record->completedUserId = $userId;
    $this->entityManager->flush();
  }

  /**
   * Method consumeReserved
   *
   * Reserves one external exchange under the caller's row lock, retaining uncertainty before consumption checks.
   *
   * @access private
   *
   * @param FederatedAuthFlowRecord $record browser-bound unexpired row locked by consume
   *
   * @return ?FederatedFlow the newly reserved flow, or null when its lifecycle forbids an exchange
   */
  private function consumeReserved(FederatedAuthFlowRecord $record): ?FederatedFlow
  {
    if ('reserved' !== $record->stage) {
      return null;
    }
    if (null !== $record->exchangeStartedAt) {
      throw new FederatedAuthException('idempotency_outcome_unknown', 'The provider exchange outcome is unknown. Start a new sign-in request.');
    }
    if (null !== $record->consumedAt) {
      return null;
    }

    $record->consumedAt = new DateTimeImmutable();
    $record->exchangeStartedAt = $record->consumedAt;
    $this->entityManager->flush();

    return $this->project($record);
  }

  /**
   * Method project
   *
   * Restores lifecycle state and opens retained secrets only when the consuming caller requests them.
   *
   * @access private
   *
   * @param FederatedAuthFlowRecord $record owner-local validated row
   * @param bool $includeSecrets whether the live exchange consumes provider/PKCE proof
   *
   * @return FederatedFlow public lifecycle snapshot
   */
  private function project(FederatedAuthFlowRecord $record, bool $includeSecrets = true): FederatedFlow
  {
    $profile = null;
    if ($includeSecrets && null !== $record->verifiedProfile && null !== $record->proofExpiresAt && $record->proofExpiresAt > new DateTimeImmutable()) {
      $data = json_decode($this->decrypt($record->verifiedProfile), true, 16, JSON_THROW_ON_ERROR);
      if (!is_array($data) || !is_string($data['provider'] ?? null) || !is_string($data['subject'] ?? null) || !is_string($data['email'] ?? null) || !is_bool($data['email_verified'] ?? null) || !is_string($data['first_name'] ?? null) || !is_string($data['last_name'] ?? null) || (null !== ($data['issuer'] ?? null) && !is_string($data['issuer']))) {
        throw new FederatedAuthException('invalid_flow', self::INVALID_FLOW_MESSAGE);
      }
      $profile = new FederatedProfile(ProviderId::from($data['provider']), $data['subject'], $data['email'], $data['email_verified'], $data['first_name'], $data['last_name'], is_string($data['issuer'] ?? null) ? $data['issuer'] : null);
    }

    return new FederatedFlow(
      stateHash: $record->stateHash,
      browserBindingHash: $record->browserBindingHash,
      provider: ProviderId::from($record->provider),
      intent: $record->intent,
      userId: $record->userId,
      codeVerifier: $includeSecrets ? $this->decrypt($record->codeVerifier) : '',
      redirectUri: $record->redirectUri,
      returnUrl: $record->returnUrl,
      expiresAt: $record->expiresAt,
      nonceHash: $record->nonceHash,
      stage: $record->stage,
      exchangeStartedAt: $record->exchangeStartedAt,
      verifiedProfile: $profile,
      proofExpiresAt: $record->proofExpiresAt,
      completedUserId: $record->completedUserId,
    );
  }
  // #endregion
}
