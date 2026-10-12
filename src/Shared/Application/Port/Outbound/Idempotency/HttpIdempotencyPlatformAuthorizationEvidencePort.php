<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

/**
 * Interface HttpIdempotencyPlatformAuthorizationEvidencePort
 *
 * Captures successful required technical permissions and role predicates independently from organization grants.
 *
 * @category Port
 */
interface HttpIdempotencyPlatformAuthorizationEvidencePort
{
  // #region Methods
  /**
   * Method tracksPrincipal
   *
   * @access public
   *
   * @param string $principalId the exact authenticated actor whose decision would be checked
   *
   * @return bool whether this request has explicitly bound that verified actor to an idempotency execution
   */
  public function tracksPrincipal(string $principalId): bool;

  /**
   * Method recordPlatform
   *
   * @access public
   *
   * @param string $principalId actual checked principal
   * @param string $kind permission, roles_any, roles_all or platform_keys_all predicate
   * @param list<string> $names required keys from the actual successful check
   *
   * @return void
   */
  public function recordPlatform(string $principalId, string $kind, array $names): void;
  // #endregion
}
