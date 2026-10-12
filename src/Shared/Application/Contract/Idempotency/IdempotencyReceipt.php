<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use DateTimeImmutable;

/**
 * Class IdempotencyReceipt
 *
 * Publishes persisted receipt facts to the application engine.
 *
 * @category Contract
 */
final readonly class IdempotencyReceipt
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $fingerprint canonical input digest
   * @param string $ownerToken ownership fence
   * @param string $state pending, writing, completed or outcome_unknown
   * @param DateTimeImmutable $leaseExpiresAt pending takeover deadline
   * @param string|null $payload opaque committed result, encrypted for secrets
   * @param bool $secret encrypted result marker
   * @param DateTimeImmutable|null $resultExpiresAt immutable replay deadline
   * @param array<string, string> $proof verified non-secret metadata
   * @param string|null $proofMac canonical proof and deadline authentication
   *
   * @return void
   */
  public function __construct(
    public string $fingerprint,
    public string $ownerToken,
    public string $state,
    public DateTimeImmutable $leaseExpiresAt,
    public ?string $payload = null,
    public bool $secret = false,
    public ?DateTimeImmutable $resultExpiresAt = null,
    public array $proof = [],
    public ?string $proofMac = null,
  ) {
  }
  // #endregion
}
