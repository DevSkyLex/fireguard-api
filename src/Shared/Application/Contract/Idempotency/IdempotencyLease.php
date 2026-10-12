<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use DateTimeImmutable;

/**
 * Class IdempotencyLease
 *
 * Carries an opaque ownership fence. It contains no request data or credentials.
 *
 * @category Contract
 */
final readonly class IdempotencyLease
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $scopeHash scoped identity digest
   * @param string $keyHash idempotency-key digest
   * @param string $fingerprint canonical input digest
   * @param string $ownerToken random ownership fence
   * @param DateTimeImmutable $expiresAt pending reservation deadline
   * @param string $principalHash principal lifecycle-purge digest
   * @param string|null $organizationHash organization lifecycle-purge digest
   *
   * @return void
   */
  public function __construct(
    public string $scopeHash,
    public string $keyHash,
    public string $fingerprint,
    public string $ownerToken,
    public DateTimeImmutable $expiresAt,
    public string $principalHash = '',
    public ?string $organizationHash = null,
  ) {
  }
  // #endregion
}
