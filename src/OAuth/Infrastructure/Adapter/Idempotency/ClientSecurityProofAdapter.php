<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use Doctrine\ORM\EntityManagerInterface;
use OAuth\Application\Contract\Client\ClientSecurityProof;
use OAuth\Application\Port\Inbound\ClientSecurityProofPort;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function password_verify;
use function preg_match;
use function strtolower;

/**
 * Class ClientSecurityProofAdapter
 *
 * Bypasses managed clients and caches for current secret/grant checks before any cached token response can be decrypted.
 *
 * @category Adapter
 */
final readonly class ClientSecurityProofAdapter implements ClientSecurityProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly auth-owned client storage
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function verify(string $clientId, string $clientSecret, string $grantType): ?ClientSecurityProof
  {
    if (1 !== preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $clientId)) {
      return null;
    }
    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT id, secret, is_active, deleted_at, security_revision, grant_types, scopes, redirect_uris FROM clients WHERE id = ?',
      [$clientId],
    );
    if (false === $row || !is_string($row['secret'] ?? null) || !password_verify($clientSecret, $row['secret']) || null !== ($row['deleted_at'] ?? null)) {
      return null;
    }
    if (!in_array($row['is_active'] ?? null, [true, 1, '1'], true)) {
      return null;
    }
    $revision = $row['security_revision'] ?? null;
    $grantTypes = array_map(strtolower(...), $this->strings($row['grant_types'] ?? null));
    if ((!is_int($revision) && !is_numeric($revision)) || (int) $revision < 1 || !in_array($grantType, $grantTypes, true)) {
      return null;
    }

    return new ClientSecurityProof($clientId, (int) $revision, $grantTypes, $this->strings($row['scopes'] ?? null), $this->strings($row['redirect_uris'] ?? null));
  }

  /**
   * Method strings
   *
   * Normalizes driver JSON scalars without admitting object values into the public proof.
   *
   * @access private
   *
   * @param mixed $value driver JSON value
   *
   * @return list<string> validated scalar items
   */
  private function strings(mixed $value): array
  {
    $decoded = is_string($value) ? json_decode($value, true) : $value;

    return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : [];
  }
  // #endregion
}
