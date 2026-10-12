<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

/**
 * Class SensitiveIdempotencyAuthorization
 *
 * Carries fresh owner-verified identity and request material for a credential-sensitive operation. Canonical input is HMACed, never persisted in plaintext.
 *
 * @category Contract
 */
final readonly class SensitiveIdempotencyAuthorization
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $principalId currently verified owner identity
   * @param mixed $canonicalInput owner-defined scalar/list/map logical input
   * @param array<string, string> $receiptProof non-secret owner proof facts
   *
   * @return void
   */
  public function __construct(public string $principalId, public mixed $canonicalInput, public array $receiptProof)
  {
  }
  // #endregion
}
