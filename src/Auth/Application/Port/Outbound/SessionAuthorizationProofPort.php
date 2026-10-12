<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Authenticates issued session claims and binds encrypted replay to current owner grants.
 *
 * @category Outbound Port
 */
interface SessionAuthorizationProofPort
{
  /**
   * @param string $userId verified current account
   *
   * @return string purpose-separated current grant fingerprint
   */
  public function currentFingerprint(string $userId): string;

  /**
   * @param string $userId issued credential owner
   * @param string $accessToken newly issued signed session credential
   *
   * @return string verified issued/current grant fingerprint
   */
  public function issuedFingerprint(string $userId, string $accessToken): string;
}
