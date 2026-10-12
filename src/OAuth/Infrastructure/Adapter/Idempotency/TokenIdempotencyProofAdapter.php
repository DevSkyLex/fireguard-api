<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\SessionAuthorizationProofPort;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Inbound\ClientSecurityProofPort;
use OAuth\Application\UseCase\Command\Token\IssueToken\{IssueTokenCommand, IssueTokenResult};
use OAuth\Domain\Exception\Token\AuthorizationException;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function array_filter;
use function array_values;
use function base64_encode;
use function explode;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function rtrim;
use function strtr;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * Class TokenIdempotencyProofAdapter
 *
 * Revalidates client credentials and cryptographically verified grant ownership/PKCE before any protocol response replay.
 *
 * @category Adapter
 */
final class TokenIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Traits
  use CryptTrait;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ClientSecurityProofPort $clients fresh owner client proof
   * @param UserSecurityRevisionPort $users current user security state
   * @param EntityManagerInterface $entityManager explicitly auth-owned grant storage
   * @param string $encryptionKey existing authenticated OAuth grant encryption key
   *
   * @return void
   */
  public function __construct(
    private readonly ClientSecurityProofPort $clients,
    private readonly UserSecurityRevisionPort $users,
    private readonly EntityManagerInterface $entityManager,
    #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')]
    string $encryptionKey,
    private readonly SessionAuthorizationProofPort $grants,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return IssueTokenCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof IssueTokenCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $client = $this->clients->verify($message->clientId, $message->clientSecret, $message->grantType);
    if (null === $client) {
      throw AuthorizationException::invalidClient('Client authentication failed.');
    }
    $requestedScopes = array_values(array_filter(explode(' ', $message->scope ?? ''), static fn (string $scope): bool => '' !== $scope));
    foreach ($requestedScopes as $scope) {
      if (!in_array($scope, $client->scopes, true)) {
        throw AuthorizationException::invalidScope('The requested scope is invalid.');
      }
    }
    $proof = ['client_id' => $client->clientId, 'client_revision' => (string) $client->securityRevision, 'grant_type' => $message->grantType];
    if (null !== $storedProof && (($storedProof->metadata['client_id'] ?? null) !== $client->clientId || ($storedProof->metadata['client_revision'] ?? null) !== (string) $client->securityRevision || ($storedProof->metadata['grant_type'] ?? null) !== $message->grantType)) {
      throw AuthorizationException::invalidClient('Client authentication failed.');
    }
    if ('authorization_code' === $message->grantType) {
      $grant = $this->decodeGrant($message->code);
      $this->assertGrantClient($grant, $message->clientId);
      $codeId = $grant['auth_code_id'] ?? null;
      $challenge = $grant['code_challenge'] ?? null;
      $verifier = $message->codeVerifier ?? '';
      if (!is_string($codeId) || !is_string($challenge) || 1 !== preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) {
        throw AuthorizationException::invalidGrant('Invalid authorization code proof.');
      }
      $method = $grant['code_challenge_method'] ?? 'plain';
      $computed = 'S256' === $method ? rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=') : $verifier;
      if (!in_array($method, ['plain', 'S256'], true) || !hash_equals($challenge, $computed) || ($grant['redirect_uri'] ?? null) !== $message->redirectUri || !in_array($message->redirectUri, $client->redirectUris, true)) {
        throw AuthorizationException::invalidGrant('Invalid authorization code proof.');
      }
      $row = $this->entityManager->getConnection()->fetchAssociative('SELECT user_identifier, client_identifier FROM auth_codes WHERE identifier = ? AND expiry > CURRENT_TIMESTAMP', [$codeId]);
      if (false === $row || ($row['client_identifier'] ?? null) !== $client->clientId) {
        throw AuthorizationException::invalidGrant('Invalid authorization code.');
      }
      $proof['grant_id'] = $codeId;
      $proof = $this->userProof($row['user_identifier'] ?? null, $proof, $storedProof);
      if (null !== $storedProof && ($storedProof->metadata['grant_id'] ?? null) !== $codeId) {
        throw AuthorizationException::invalidGrant('Invalid authorization code.');
      }
    } elseif ('refresh_token' === $message->grantType) {
      $grant = $this->decodeGrant($message->refreshToken);
      $this->assertGrantClient($grant, $message->clientId);
      $refreshId = $grant['refresh_token_id'] ?? null;
      if (!is_string($refreshId)) {
        throw AuthorizationException::invalidGrant('Invalid refresh token.');
      }
      $proof = $this->userProof($grant['user_id'] ?? null, $proof, $storedProof);
      $proof['grant_id'] = $refreshId;
      if (null !== $storedProof && !in_array($refreshId, [$storedProof->metadata['grant_id'] ?? null, $storedProof->metadata['issued_refresh_id'] ?? null], true)) {
        throw AuthorizationException::invalidGrant('Invalid refresh token.');
      }
    } elseif ('client_credentials' !== $message->grantType) {
      throw AuthorizationException::unsupportedGrantType('The grant type is not supported.');
    }
    if (null !== $storedProof) {
      $this->assertIssuedTokensCurrent($storedProof, $client->clientId);
    }

    return new SensitiveIdempotencyAuthorization('client:' . $client->clientId, [
      'grant_type' => $message->grantType,
      'client_id' => $message->clientId,
      'client_secret' => $message->clientSecret,
      'scope' => $message->scope,
      'code' => $message->code,
      'redirect_uri' => $message->redirectUri,
      'code_verifier' => $message->codeVerifier,
      // Refresh lineage is verified from the signed receipt and current daughter rows.
      'refresh_owner' => 'refresh_token' === $message->grantType ? ($proof['user_id'] ?? '') : null,
    ], $proof + ['naturalKeyAllowed' => 'client_credentials' === $message->grantType ? 'false' : 'true', 'naturalKeyIdentity' => $message->grantType . ':' . ($proof['grant_id'] ?? '')]);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof IssueTokenCommand || !$result instanceof IssueTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $authorization = $this->authorize($message, null);
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT expiry FROM access_tokens WHERE identifier = ? AND client_identifier = ? AND is_revoked = FALSE', [$result->tokenId, $message->clientId]);
    if (false === $row || !is_string($row['expiry'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $proof = $authorization->receiptProof;
    $proof['issued_access_id'] = $result->tokenId;
    $proof['access_expires_at'] = (string) new DateTimeImmutable($row['expiry'], new DateTimeZone('UTC'))->getTimestamp();
    if (null !== $result->refreshToken) {
      $refresh = $this->decodeGrant($result->refreshToken);
      $refreshId = $refresh['refresh_token_id'] ?? null;
      if (!is_string($refreshId)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $proof['issued_refresh_id'] = $refreshId;
    }

    return $proof;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof IssueTokenResult) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new IssueTokenResult($result->accessToken, $result->tokenType, max(0, (int) ($proof->metadata['access_expires_at'] ?? 0) - time()), $result->tokenId, $result->refreshToken, $result->scope, $result->idToken);
  }

  /**
   * @param ?string $token authenticated encrypted grant credential
   *
   * @return array<string, mixed> verified grant facts
   */
  private function decodeGrant(?string $token): array
  {
    try {
      if (null === $token || '' === $token) {
        throw AuthorizationException::invalidGrant('Invalid grant.');
      }
      $grant = json_decode($this->decrypt($token), true, 32, JSON_THROW_ON_ERROR);
      if (!is_array($grant) || !is_int($grant['expire_time'] ?? null) || $grant['expire_time'] <= time()) {
        throw AuthorizationException::invalidGrant('Invalid or expired grant.');
      }

      /** @var array<string, mixed> $grant */
      return $grant;
    } catch (Throwable) {
      throw AuthorizationException::invalidGrant('Invalid or expired grant.');
    }
  }

  /**
   * @param array<string, mixed> $grant verified grant
   * @param string $clientId current client
   *
   * @return void
   */
  private function assertGrantClient(array $grant, string $clientId): void
  {
    if (($grant['client_id'] ?? null) !== $clientId) {
      throw AuthorizationException::invalidGrant('The grant does not belong to this client.');
    }
  }

  /**
   * @param mixed $userId verified grant owner
   * @param array<string, string> $proof current facts
   * @param ?IdempotencyProof $storedProof authenticated receipt
   *
   * @return array<string, string> current owner proof
   */
  private function userProof(mixed $userId, array $proof, ?IdempotencyProof $storedProof): array
  {
    if (!is_string($userId) || '' === $userId) {
      throw AuthorizationException::invalidGrant('The grant owner is unavailable.');
    }
    $current = $this->users->current($userId);
    if (null === $current || !$current->active || (null !== $storedProof && (($storedProof->metadata['user_id'] ?? null) !== $userId || ($storedProof->metadata['security_revision'] ?? null) !== (string) $current->securityRevision))) {
      throw AuthorizationException::invalidGrant('The grant owner is unavailable.');
    }
    $proof['user_id'] = $userId;
    $proof['security_revision'] = (string) $current->securityRevision;
    $proof['authorization_fingerprint'] = $this->grants->currentFingerprint($userId);
    if (null !== $storedProof && ($storedProof->metadata['authorization_fingerprint'] ?? null) !== $proof['authorization_fingerprint']) {
      throw AuthorizationException::invalidGrant('The grant owner is unavailable.');
    }

    return $proof;
  }

  /**
   * @param IdempotencyProof $proof authenticated receipt
   * @param string $clientId current client
   *
   * @return void
   */
  private function assertIssuedTokensCurrent(IdempotencyProof $proof, string $clientId): void
  {
    $accessId = $proof->metadata['issued_access_id'] ?? '';
    $exists = $this->entityManager->getConnection()->fetchOne('SELECT identifier FROM access_tokens WHERE identifier = ? AND client_identifier = ? AND is_revoked = FALSE AND expiry > CURRENT_TIMESTAMP', [$accessId, $clientId]);
    if (!is_string($exists)) {
      throw AuthorizationException::invalidGrant('The issued grant is no longer current.');
    }
    if (isset($proof->metadata['issued_refresh_id'])) {
      $refresh = $this->entityManager->getConnection()->fetchOne('SELECT identifier FROM refresh_tokens WHERE identifier = ? AND access_token_identifier = ? AND client_identifier = ? AND is_revoked = FALSE AND expiry > CURRENT_TIMESTAMP', [$proof->metadata['issued_refresh_id'], $accessId, $clientId]);
      if (!is_string($refresh)) {
        throw AuthorizationException::invalidGrant('The issued grant is no longer current.');
      }
    }
  }
  // #endregion
}
