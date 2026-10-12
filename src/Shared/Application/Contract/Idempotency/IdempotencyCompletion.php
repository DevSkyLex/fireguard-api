<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use DateTimeImmutable;

/**
 * Class IdempotencyCompletion
 *
 * Carries the opaque result and authenticated proof for one same-transaction completion.
 * Its deadlines and encoded bytes come from the owning engine without reinterpretation.
 *
 * @category Contract
 */
final readonly class IdempotencyCompletion
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $payload opaque application-result bytes
   * @param bool $secret encryption marker
   * @param DateTimeImmutable $completedAt first completion time
   * @param DateTimeImmutable $expiresAt immutable replay deadline
   * @param array<string, string> $proof verified non-secret metadata
   * @param string $proofMac canonical proof authentication
   * @param string|null $organizationHash result-owner organization purge digest
   *
   * @return void
   */
  public function __construct(
    public string $payload,
    public bool $secret,
    public DateTimeImmutable $completedAt,
    public DateTimeImmutable $expiresAt,
    public array $proof = [],
    public string $proofMac = '',
    public ?string $organizationHash = null,
  ) {
  }
  // #endregion
}
