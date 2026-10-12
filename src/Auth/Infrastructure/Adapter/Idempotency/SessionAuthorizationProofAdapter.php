<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionAuthorizationProofPort};
use Authorization\Application\Contract\Grant\UserGrantSnapshot;
use Authorization\Application\Port\Inbound\UserGrantSnapshotPort;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyFingerprintPort;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function sort;

/**
 * Uses verified emitted JWT claims and a fresh owner grant projection before secret decryption.
 *
 * @category Adapter
 */
final readonly class SessionAuthorizationProofAdapter implements SessionAuthorizationProofPort
{
  /**
   * @param JwtTokenServicePort $tokens signature/expiry verification under the Auth issuer
   * @param UserSecurityRevisionPort $users fresh owner tenant and security state
   * @param UserGrantSnapshotPort $grants uncached current grants and catalog facts
   * @param IdempotencyFingerprintPort $fingerprints dedicated HMAC capability
   */
  public function __construct(private JwtTokenServicePort $tokens, private UserSecurityRevisionPort $users, private UserGrantSnapshotPort $grants, private IdempotencyFingerprintPort $fingerprints)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function currentFingerprint(string $userId): string
  {
    return $this->fingerprint($this->current($userId));
  }

  /**
   * {@inheritDoc}
   */
  public function issuedFingerprint(string $userId, string $accessToken): string
  {
    // The Auth decoder verifies the signature and expiry; the explicit family/sub checks prevent cross-purpose claims.
    $claims = $this->tokens->decodePreAuthToken($accessToken);
    if (null === $claims || ($claims['sub'] ?? null) !== $userId || ($claims['_fireguard_token_use'] ?? null) !== 'auth_session') {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $current = $this->current($userId);
    if ((isset($claims['roles']) && $this->names($claims['roles']) !== $this->names($current->roles)) || (isset($claims['permissions']) && $this->names($claims['permissions']) !== $this->names($current->permissions))) {
      throw new IdempotencyDeniedException('owner_lost');
    }

    return $this->fingerprint($current);
  }

  /**
   * @param string $userId verified current owner
   *
   * @return UserGrantSnapshot fresh authority snapshot
   */
  private function current(string $userId): UserGrantSnapshot
  {
    $user = $this->users->current($userId) ?? throw new IdempotencyDeniedException('owner_lost');

    return $this->grants->current($userId, $user->tenantId);
  }

  /**
   * @param UserGrantSnapshot $snapshot authoritative current facts
   *
   * @return string private normalized claim/catalog fingerprint
   */
  private function fingerprint(UserGrantSnapshot $snapshot): string
  {
    return $this->fingerprints->digest('session.authorization', ['roles' => $this->names($snapshot->roles), 'permissions' => $this->names($snapshot->permissions), 'catalog' => $snapshot->catalogFacts]);
  }

  /**
   * @param mixed $names verified JWT names or published owner list
   *
   * @return list<string> normalized finite claim set
   */
  private function names(mixed $names): array
  {
    if (!is_array($names)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $normalized = [];
    foreach ($names as $name) {
      if (!is_string($name)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $normalized[] = $name;
    }
    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    return $normalized;
  }
}
