<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAccount;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyAccountPort;

use function in_array;
use function is_numeric;
use function is_string;

/**
 * Class UserHttpIdempotencyAccountAdapter
 *
 * Publishes fresh current User account facts through the Shared receipt contract.
 *
 * @category Adapter
 */
final readonly class UserHttpIdempotencyAccountAdapter implements HttpIdempotencyAccountPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected auth connection, bypassing managed account caches
   */
  public function __construct(private Connection $connection, private ?AuthenticationPolicyPort $authenticationPolicy = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function current(string $principalId): ?HttpIdempotencyAccount
  {
    $row = $this->connection->fetchAssociative('SELECT id, tenant_id, status, email_verified, security_revision FROM users WHERE id = :id', ['id' => $principalId]);
    if (false === $row || !is_string($row['id'] ?? null) || !is_numeric($row['security_revision'] ?? null) || (int) $row['security_revision'] < 1) {
      return null;
    }
    $tenantId = $row['tenant_id'] ?? null;
    if (null !== $tenantId && !is_string($tenantId)) {
      return null;
    }

    $eligible = 'active' === ($row['status'] ?? null) || ('pending_verification' === ($row['status'] ?? null) && null !== $this->authenticationPolicy && !$this->authenticationPolicy->emailVerificationRequired());

    return new HttpIdempotencyAccount($row['id'], $tenantId, (int) $row['security_revision'], $eligible, in_array($row['email_verified'] ?? false, [true, 1, '1', 't'], true));
  }
  // #endregion
}
