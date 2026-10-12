<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Contract\Token\TokenRevocationProof;
use OAuth\Application\Port\Inbound\{ClientLifecycleProofPort, TokenRevocationProofPort};
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use Throwable;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Verifies signatures/authenticated ciphertext before native grant identifiers can drive replay scope.
 *
 * @category Adapter
 */
final class TokenRevocationProofAdapter implements TokenRevocationProofPort
{
  use CryptTrait;

  public function __construct(
    private readonly Connection $connection,
    private readonly JwtParserPort $jwt,
    private readonly ClientLifecycleProofPort $clients,
    private readonly UserSecurityRevisionPort $users,
    string $encryptionKey,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }

  public function prove(string $token, ?string $tokenTypeHint = null): TokenRevocationProof
  {
    if ('access_token' === $tokenTypeHint) {
      return $this->access($token) ?? $this->refresh($token) ?? new TokenRevocationProof('unknown');
    }

    return $this->refresh($token) ?? $this->access($token) ?? new TokenRevocationProof('unknown');
  }

  private function access(string $token): ?TokenRevocationProof
  {
    if (!$this->jwt->validate($token)) {
      return null;
    }
    $claims = $this->jwt->parse($token);
    $id = $claims['jti'] ?? null;
    if (!is_string($id) || '' === $id) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT identifier, client_identifier, user_identifier, is_revoked FROM access_tokens WHERE identifier = ?', [$id]);

    return false === $row ? null : $this->owner('access_token', $row);
  }

  private function refresh(string $token): ?TokenRevocationProof
  {
    try {
      $payload = json_decode($this->decrypt($token), true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
      return null;
    }
    if (!is_array($payload) || !is_string($payload['refresh_token_id'] ?? null) || !is_string($payload['client_id'] ?? null)) {
      return null;
    }
    $row = $this->connection->fetchAssociative(
      'SELECT r.identifier, r.client_identifier, a.user_identifier, r.is_revoked FROM refresh_tokens r JOIN access_tokens a ON a.identifier = r.access_token_identifier WHERE r.identifier = ?',
      [$payload['refresh_token_id']],
    );
    if (false === $row || ($row['client_identifier'] ?? null) !== $payload['client_id'] || (isset($payload['user_id']) && ($row['user_identifier'] ?? null) !== $payload['user_id'])) {
      return null;
    }

    return $this->owner('refresh_token', $row);
  }

  /**
   * @param array<string, mixed> $row verified native stored grant
   */
  private function owner(string $kind, array $row): ?TokenRevocationProof
  {
    $id = $row['identifier'] ?? null;
    $clientId = $row['client_identifier'] ?? null;
    $userId = $row['user_identifier'] ?? null;
    if (!is_string($id) || !is_string($clientId) || (null !== $userId && !is_string($userId))) {
      return null;
    }
    $client = $this->clients->current($clientId);
    $user = null === $userId ? null : $this->users->current($userId);
    if (null === $client || (null !== $userId && null === $user)) {
      return null;
    }

    return new TokenRevocationProof($kind, $id, $clientId, $userId, $client->securityRevision, $user?->securityRevision, true === ($row['is_revoked'] ?? null));
  }
}
