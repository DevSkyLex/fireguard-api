<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

use DateTimeImmutable;

/**
 * Fresh non-secret credential lifecycle facts used to authorize a signed receipt.
 *
 * @category Contract
 */
final readonly class CredentialLifecycleProof
{
  /**
   * @param string $userId owning account
   * @param int $securityRevision current credential revision
   * @param string $status current account state
   * @param string $email current bound mailbox
   * @param bool $emailVerified current mailbox proof
   * @param bool $passwordConfigured whether a password credential exists
   * @param int $failedLoginAttempts durable credential failure count
   * @param ?DateTimeImmutable $emailOwnershipVerifiedAt current explicit mailbox ownership proof
   */
  public function __construct(public string $userId, public int $securityRevision, public string $status, public string $email, public bool $emailVerified, public bool $passwordConfigured, public int $failedLoginAttempts, public ?DateTimeImmutable $emailOwnershipVerifiedAt = null)
  {
  }
}
