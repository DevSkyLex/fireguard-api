<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\SessionAuthorizationProofPort;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Contract\Token\{AuthorizationGrantOutcome, AuthorizationReceiptRequest};
use OAuth\Application\Port\Outbound\Token\AuthorizationReceiptPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPort, IdempotencyFingerprintPort};
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function parse_str;
use function parse_url;
use function preg_match;
use function time;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_QUERY;

/**
 * Records a validated authorization grant before its final auth commit.
 * The native identity binds the current browser session, client, state and PKCE request.
 *
 * @category Adapter
 */
final class AuthorizationIdempotencyAdapter implements AuthorizationReceiptPort
{
  use CryptTrait;

  /**
   * @param HttpIdempotencyPort $engine explicitly auth-bound receipts
   * @param IdempotencyFingerprintPort $fingerprints dedicated native identity HMAC
   * @param RootTransactionManagerPort $transaction final auth commit owner
   * @param EntityManagerInterface $entityManager explicitly auth-owned grant persistence
   * @param UserSecurityRevisionPort $users current owner security state
   * @param string $encryptionKey League authenticated grant decoding key
   */
  public function __construct(private readonly HttpIdempotencyPort $engine, private readonly IdempotencyFingerprintPort $fingerprints, private readonly RootTransactionManagerPort $transaction, private readonly EntityManagerInterface $entityManager, private readonly UserSecurityRevisionPort $users, private readonly SessionAuthorizationProofPort $grants, #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')] string $encryptionKey)
  {
    $this->setEncryptionKey($encryptionKey);
  }

  /**
   * @param AuthorizationReceiptRequest $request League-validated current browser authorization facts
   * @param callable():AuthorizationGrantOutcome $completion lock, fresh session guard and native grant issuance
   *
   * @return AuthorizationGrantOutcome original or currently authorized protocol result
   */
  public function complete(AuthorizationReceiptRequest $request, callable $completion, callable $authorizeCurrent): AuthorizationGrantOutcome
  {
    $this->assertAuthorization($authorizeCurrent);
    $params = $request->parameters;
    $tokenId = $request->tokenId;
    $userId = $request->userId;
    $clientId = $this->authorizationClientId($request);
    $clientRevision = $this->currentClientRevision($clientId);
    $user = $this->users->current($userId);
    if (null === $user || !$user->active) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $canonical = ['parameters' => $params, 'session' => $tokenId, 'family' => $request->tokenFamily];
    $key = $this->executionKey($request->keys, $canonical);
    $scope = new IdempotencyScope($userId, null, null, $request->operationName, $request->resourceUri);
    $authorizeReplay = function (IdempotencyProof $proof) use ($userId, $clientId): void {
      $this->assertReplayProof($proof, $userId, $clientId);
    };
    $begin = $this->engine->begin($scope, $key, $canonical, $authorizeReplay);
    if (null !== $begin->replay) {
      return $this->restore($begin->replay);
    }
    $lease = $begin->lease ?? throw new IdempotencyDeniedException('result_unavailable');

    try {
      return $this->transaction->transactional(function () use ($completion, $lease, $userId, $clientId, $clientRevision, $user): AuthorizationGrantOutcome {
        $this->engine->claimWithinTransaction($lease);
        $this->assertLockedRevisions($clientId, $clientRevision, $userId, $user->securityRevision);
        $response = $completion();
        $location = $response->redirectLocation;
        $code = null === $location ? '' : $this->authorizationCode($location);
        $proof = ['user_id' => $userId, 'security_revision' => (string) $user->securityRevision, 'client_id' => $clientId, 'client_revision' => (string) $clientRevision, 'auth_code_id' => $code, 'success' => '' === $code ? '0' : '1'];
        $proof['authorization_fingerprint'] = $this->grants->currentFingerprint($userId);
        $payload = json_encode(['version' => 1, 'status' => $response->statusCode, 'location' => $location, 'content' => $response->content, 'content_type' => $response->contentType], JSON_THROW_ON_ERROR);
        $this->engine->beforeCommit($lease, new IdempotencySnapshot($response->statusCode, $payload, secret: true, proof: $proof));

        return $response;
      });
    } catch (CommitOutcomeUnknownException $failure) {
      $this->engine->commitUnknown($lease);
      $this->assertAuthorization($authorizeCurrent);
      $snapshot = $this->engine->reconcile($lease, $authorizeReplay);
      if (null !== $snapshot) {
        return $this->restore($snapshot);
      }

      throw $failure;
    } catch (Throwable $failure) {
      $this->engine->confirmedRollback($lease);

      throw $failure;
    }
  }

  /**
   * Method authorizationClientId
   *
   * Validates the native browser operation, client, state and PKCE identity before selecting a receipt.
   *
   * @access private
   *
   * @param AuthorizationReceiptRequest $request validated browser protocol facts
   *
   * @return string requesting client after native identity validation
   */
  private function authorizationClientId(AuthorizationReceiptRequest $request): string
  {
    $clientId = $request->parameters['client_id'] ?? null;
    $state = $request->parameters['state'] ?? null;
    $pkce = $request->parameters['code_challenge'] ?? null;
    if (!in_array($request->operationName, ['authorize', 'grant_consent'], true) || '' === $request->tokenId || !is_string($clientId) || (null !== $state && !is_string($state)) || !is_string($pkce) || 1 !== preg_match('/\A[A-Za-z0-9._~\-]{43,128}\z/', $pkce)) {
      throw new IdempotencyDeniedException('owner_lost');
    }

    return $clientId;
  }

  /**
   * Method currentClientRevision
   *
   * Loads the fresh active client epoch before selecting a receipt.
   *
   * @access private
   *
   * @param string $clientId currently validated client identifier
   *
   * @return int fresh active client epoch before receipt selection
   */
  private function currentClientRevision(string $clientId): int
  {
    $client = $this->entityManager->getConnection()->fetchAssociative('SELECT security_revision FROM clients WHERE id = ? AND is_active = TRUE AND deleted_at IS NULL', [$clientId]);
    if (false === $client || (!is_int($client['security_revision']) && !is_numeric($client['security_revision']))) {
      throw new IdempotencyDeniedException('owner_lost');
    }

    return (int) $client['security_revision'];
  }

  /**
   * Method executionKey
   *
   * Rejects ambiguous headers and derives native protocol identity only when no key was supplied.
   *
   * @access private
   *
   * @param list<string|null> $keys caller execution keys
   * @param array<string, mixed> $canonical validated native request identity
   *
   * @return string explicit or native protocol execution identity
   */
  private function executionKey(array $keys, array $canonical): string
  {
    if ([] !== $keys && (1 !== count($keys) || !is_string($keys[0]) || 1 !== preg_match('/^[\x21-\x7e]{1,255}$/D', $keys[0]))) {
      throw new IdempotencyDeniedException('key_invalid');
    }

    return $keys[0] ?? $this->fingerprints->digest('oauth.authorization.key', $canonical);
  }

  /**
   * Method assertLockedRevisions
   *
   * Acquires shared owner locks and rejects credential epochs changed since receipt reservation.
   *
   * @access private
   *
   * @param string $clientId validated client identity
   * @param int $clientRevision epoch selected before execution
   * @param string $userId verified browser owner
   * @param int $userRevision epoch selected before execution
   *
   * @return void
   */
  private function assertLockedRevisions(string $clientId, int $clientRevision, string $userId, int $userRevision): void
  {
    $lockedClient = $this->entityManager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ? AND is_active = TRUE AND deleted_at IS NULL FOR SHARE', [$clientId]);
    $lockedUser = $this->entityManager->getConnection()->fetchOne('SELECT security_revision FROM users WHERE id = ? AND status = ? FOR SHARE', [$userId, 'active']);
    if ((!is_int($lockedClient) && !is_numeric($lockedClient)) || (int) $lockedClient !== $clientRevision || (!is_int($lockedUser) && !is_numeric($lockedUser)) || (int) $lockedUser !== $userRevision) {
      throw new IdempotencyDeniedException('owner_lost');
    }
  }

  /**
   * Method assertReplayProof
   *
   * Authorizes the selected completed receipt before its encrypted result is opened.
   *
   * @access private
   *
   * @param IdempotencyProof $stored authenticated proof from the receipt selected by the engine
   * @param string $userId currently verified browser account
   * @param string $clientId currently validated requesting client
   *
   * @return void
   */
  private function assertReplayProof(IdempotencyProof $stored, string $userId, string $clientId): void
  {
    $proof = $stored->metadata;
    $user = $this->users->current($userId);
    $client = $this->entityManager->getConnection()->fetchOne('SELECT security_revision FROM clients WHERE id = ? AND is_active = TRUE AND deleted_at IS NULL', [$clientId]);
    if (null === $user || !$user->active || ($proof['user_id'] ?? null) !== $userId || ($proof['security_revision'] ?? null) !== (string) $user->securityRevision || ($proof['client_id'] ?? null) !== $clientId || (!is_int($client) && !is_numeric($client)) || ($proof['client_revision'] ?? null) !== (string) $client) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    if (($proof['authorization_fingerprint'] ?? null) !== $this->grants->currentFingerprint($userId)) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $this->assertCodeCurrent($proof, $userId, $clientId);
  }

  /**
   * Method assertCodeCurrent
   *
   * Rejects consumed, expired or differently owned authorization codes before result opening.
   *
   * @access private
   *
   * @param array<string,string> $proof MAC-verified code lineage before ciphertext opens
   * @param string $userId current browser account
   * @param string $clientId currently active requesting client
   */
  private function assertCodeCurrent(array $proof, string $userId, string $clientId): void
  {
    if ('' !== ($proof['auth_code_id'] ?? '')) {
      $usable = $this->entityManager->getConnection()->fetchOne('SELECT identifier FROM auth_codes WHERE identifier = ? AND user_identifier = ? AND client_identifier = ? AND is_revoked = FALSE AND expiry > CURRENT_TIMESTAMP', [$proof['auth_code_id'], $userId, $clientId]);
      if (!is_string($usable)) {
        throw new IdempotencyDeniedException('owner_lost');
      }
    }
  }

  /**
   * Method authorizationCode
   *
   * Decodes the authenticated issued code identity while preserving native error redirects.
   *
   * @access private
   *
   * @param string|null $location freshly generated League redirect
   *
   * @return string authenticated logical authorization code identity
   */
  private function authorizationCode(?string $location): string
  {
    if (null === $location) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $query = parse_url($location, PHP_URL_QUERY);
    $params = [];
    if (is_string($query)) {
      parse_str($query, $params);
    }
    $code = $params['code'] ?? null;
    if (!is_string($code)) {
      if (is_string($params['error'] ?? null)) {
        return '';
      }

      throw new IdempotencyDeniedException('result_unavailable');
    }
    $grant = json_decode($this->decrypt($code), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($grant) || !is_string($grant['auth_code_id'] ?? null) || !is_int($grant['expire_time'] ?? null) || $grant['expire_time'] <= time()) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $grant['auth_code_id'];
  }

  /**
   * Method restore
   *
   * Restores only the authenticated versioned protocol result snapshot.
   *
   * @access private
   *
   * @param IdempotencySnapshot $snapshot authenticated application protocol outcome
   *
   * @return AuthorizationGrantOutcome original authenticated owner protocol outcome
   */
  private function restore(IdempotencySnapshot $snapshot): AuthorizationGrantOutcome
  {
    $payload = json_decode($snapshot->body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || 1 !== ($payload['version'] ?? null) || !is_int($payload['status'] ?? null) || !is_string($payload['location'] ?? null) || !is_string($payload['content'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new AuthorizationGrantOutcome($payload['status'], $payload['location'], $payload['content'], is_string($payload['content_type'] ?? null) ? $payload['content_type'] : null, true);
  }

  /**
   * Method assertAuthorization
   *
   * Rechecks current browser authorization before execution and after an uncertain commit.
   *
   * @access private
   *
   * @param callable():bool $authorizeCurrent live owner proof checked again after an uncertain commit
   *
   * @return void
   */
  private function assertAuthorization(callable $authorizeCurrent): void
  {
    if (!$authorizeCurrent()) {
      throw new IdempotencyDeniedException('owner_lost');
    }
  }
}
