<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use LogicException;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, SensitiveIdempotencyProofPort};

/**
 * Class HttpIdempotencyPolicyRegistry
 *
 * Resolves one explicit owner gate per operation or sensitive command, rejecting ambiguous registrations.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyPolicyRegistry
{
  // #region Properties
  /**
   * @var list<HttpIdempotencyReplayAuthorizationPort> ordinary owner gates
   */
  private array $ordinary;

  /**
   * @var list<SensitiveIdempotencyProofPort> credential-sensitive owner gates
   */
  private array $sensitive;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<HttpIdempotencyReplayAuthorizationPort> $ordinary ordinary operation gates
   * @param iterable<SensitiveIdempotencyProofPort> $sensitive sensitive command proof policies
   *
   * @return void
   */
  public function __construct(iterable $ordinary, iterable $sensitive)
  {
    $owners = [];
    foreach ($ordinary as $owner) {
      $owners[] = $owner;
    }
    $this->ordinary = $owners;
    $proofs = [];
    foreach ($sensitive as $proof) {
      $proofs[] = $proof;
    }
    $this->sensitive = $proofs;
  }
  // #endregion

  // #region Methods
  /**
   * Method ordinary
   *
   * @access public
   *
   * @param string $operation the exact operation name
   *
   * @return HttpIdempotencyReplayAuthorizationPort|null its owner gate when registered
   */
  public function ordinary(string $operation): ?HttpIdempotencyReplayAuthorizationPort
  {
    $found = null;
    foreach ($this->ordinary as $owner) {
      if ($owner->supports($operation)) {
        if (null !== $found) {
          throw new LogicException('HTTP replay authorization ownership is duplicated.');
        }
        $found = $owner;
      }
    }

    return $found;
  }

  /**
   * Method sensitive
   *
   * @access public
   *
   * @param string $commandClass the declared route-primary command
   *
   * @return SensitiveIdempotencyProofPort|null its current credential/proof policy when registered
   */
  public function sensitive(string $commandClass): ?SensitiveIdempotencyProofPort
  {
    $found = null;
    foreach ($this->sensitive as $owner) {
      if ($owner->supports($commandClass)) {
        if (null !== $found) {
          throw new LogicException('Sensitive HTTP replay proof ownership is duplicated.');
        }
        $found = $owner;
      }
    }

    return $found;
  }
  // #endregion
}
